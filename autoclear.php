<?php
/**
 * Auto-Clear für Songrequest-Playlist mit 20s-Schwelle & State-Tracking
 *
 * Regeln:
 *  A) Aktiver Player spielt Track n in deiner Playlist -> lösche Positionen < n (0..n-1).
 *  B) End-Wrap (pausiert, 0ms, Index 0): lösche nur, wenn der zuvor beobachtete Track
 *     mind. bis (duration - 20000ms) gespielt wurde (20s Puffer).
 *  C) Kein aktiver Player -> nichts löschen.
 */

require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$playlistId = envv('SPOTIFY_PLAYLIST_ID');
$STATE_FILE = __DIR__ . '/autoclear_state.json';
$NEAR_END_PAD_MS = 20000; // 20 Sekunden Puffer

// State laden/speichern
function load_state($file) {
    if (is_file($file)) {
        $js = @file_get_contents($file);
        $st = @json_decode((string)$js, true);
        if (is_array($st)) return $st;
    }
    return [];
}
function save_state($file, array $st) {
    @file_put_contents($file, json_encode($st, JSON_UNESCAPED_UNICODE));
}

try {
    $token = get_access_token_from_refresh();

    // 1) Playlist minimal laden
    $pl = spotify_api(
        'GET',
        "https://api.spotify.com/v1/playlists/{$playlistId}?fields=snapshot_id,tracks.total,tracks.items(track(id,uri),added_at)",
        $token
    );
    $snapshotId = $pl['snapshot_id'] ?? null;
    $items      = $pl['tracks']['items'] ?? [];
    $total      = (int)($pl['tracks']['total'] ?? count($items));

    if ($total === 0 || empty($items)) {
        // Playlist leer -> State zurücksetzen
        save_state($STATE_FILE, []);
        json_ok(['ok'=>true,'skipped'=>'empty_playlist']);
    }

    // Arrays der Playlist-IDs/URIs
    $ids = [];
    $uris = [];
    foreach ($items as $i => $it) {
        $ids[$i]  = $it['track']['id']  ?? null;
        $uris[$i] = $it['track']['uri'] ?? null;
    }

    // 2) Playerstatus (200 aktiv / 204 kein Player)
    $player = spotify_api('GET', 'https://api.spotify.com/v1/me/player', $token, null, [200,204]);

    $state = load_state($STATE_FILE);
    $deletedPositions = [];
    $mode = null;

    if ($player !== 204 && is_array($player) && !empty($player['item']['id'])) {
        // --- Aktiver Player vorhanden
        $isPlaying  = (bool)($player['is_playing'] ?? false);
        $progressMs = (int)($player['progress_ms'] ?? 0);
        $currId     = $player['item']['id'] ?? null;
        $durationMs = (int)($player['item']['duration_ms'] ?? 0);

        // Index des aktuellen Tracks in deiner Playlist
        $currentIndex = ($currId && $ids) ? array_search($currId, $ids, true) : false;

        // State (max gesehener Fortschritt) updaten
        if ($currId) {
            if (!isset($state['tracks'][$currId])) {
                $state['tracks'][$currId] = ['maxProgressMs' => 0, 'durationMs' => $durationMs];
            }
            $state['tracks'][$currId]['durationMs'] = $durationMs ?: ($state['tracks'][$currId]['durationMs'] ?? 0);
            if ($progressMs > ($state['tracks'][$currId]['maxProgressMs'] ?? 0)) {
                $state['tracks'][$currId]['maxProgressMs'] = $progressMs;
            }
        }

        // A) Weitergeschaltet -> lösche alle davor (0..currentIndex-1)
        if ($currentIndex !== false && $currentIndex > 0) {
            for ($i = 0; $i < $currentIndex; $i++) {
                if ($uris[$i]) $deletedPositions[] = $i;
            }
            $mode = 'active_player_advanced';
        }
        // B) End-Wrap: pausiert, 0ms, Index 0 -> nur löschen, wenn vorher Near-End belegt
        elseif ($currentIndex !== false && !$isPlaying && $progressMs === 0 && $currentIndex === 0) {
            $lastIdx        = $state['lastIdx']        ?? null;
            $lastTotal      = $state['lastTotal']      ?? null;
            $lastCurrId     = $state['lastCurrId']     ?? null;
            $lastProgMs     = $state['lastProgressMs'] ?? null;
            $lastDurMs      = $state['lastDurationMs'] ?? null;

            $hadNearEnd = ($lastDurMs > 0) && ($lastProgMs !== null) && ($lastProgMs >= max(0, $lastDurMs - $NEAR_END_PAD_MS));
            $wasAtLast  = ($lastIdx !== null && $lastTotal !== null && $lastIdx === $lastTotal - 1);

            if ($wasAtLast && $hadNearEnd) {
                // Alles löschen (in sauberer Queue bleibt am Ende genau 1 Track übrig)
                for ($i = 0; $i < $total; $i++) {
                    if ($uris[$i]) $deletedPositions[] = $i;
                }
                $mode = 'end_wrapped_confirmed';
            } else {
                $mode = 'at_first_paused_no_confirm';
            }
        } else {
            $mode = 'nothing_to_delete_yet';
        }

        // State für nächsten Poll aktualisieren
        $state['ts']            = time();
        $state['lastCurrId']    = $currId;
        $state['lastIdx']       = is_int($currentIndex) ? $currentIndex : null;
        $state['lastTotal']     = $total;
        $state['lastProgressMs']= $progressMs;
        $state['lastDurationMs']= $durationMs;
        save_state($STATE_FILE, $state);

    } else {
        // --- Kein aktiver Player -> nichts löschen (Requests sollen liegen bleiben)
        // Optional könnte man hier den State leeren/halten; wir leeren nur die "last"-Belege, nicht die Track-Historie.
        $state['lastCurrId'] = null;
        $state['lastIdx']    = null;
        $state['lastTotal']  = $total;
        save_state($STATE_FILE, $state);

        json_ok(['ok'=>true,'skipped'=>'no_active_player_wait']);
    }

    if (empty($deletedPositions)) {
        json_ok([
            'ok'=>true,
            'skipped'=>$mode,
            'status'=>[
                'total'=>$total,
                'state_lastIdx'=>$state['lastIdx'] ?? null,
                'state_lastTotal'=>$state['lastTotal'] ?? null
            ]
        ]);
    }

    // 3) positionsgenau löschen (Duplikat-sicher) mit snapshot_id
    $byUri = [];
    foreach ($deletedPositions as $pos) {
        $u = $uris[$pos] ?? null;
        if (!$u) continue;
        if (!isset($byUri[$u])) $byUri[$u] = [];
        $byUri[$u][] = $pos;
    }
    $tracksPayload = [];
    foreach ($byUri as $u => $positions) {
        $tracksPayload[] = ['uri' => $u, 'positions' => array_values($positions)];
    }

    // Optional: Dry-Run via ?dry=1
    if (isset($_GET['dry']) && $_GET['dry'] == '1') {
        json_ok([
            'ok'=>true,
            'dry_run'=>true,
            'mode'=>$mode,
            'will_delete_count'=>count($deletedPositions),
            'positions'=>$deletedPositions,
            'grouped'=>$tracksPayload
        ]);
    }

    $payload = [
        'tracks'      => $tracksPayload,
        'snapshot_id' => $snapshotId
    ];
    $res = spotify_api('DELETE', "https://api.spotify.com/v1/playlists/{$playlistId}/tracks", $token, $payload);

    json_ok([
        'ok'=>true,
        'deleted_count'=>count($deletedPositions),
        'positions'=>$deletedPositions,
        'mode'=>$mode,
        'api_result'=>$res
    ]);

} catch (Throwable $e) {
    json_fail(500, 'autoclear_error: '.$e->getMessage());
}
