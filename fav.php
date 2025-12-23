<?php
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$favPlaylistId = envv('SPOTIFY_FAV_PLAYLIST_ID');
$token = get_access_token_from_refresh();

/*
 * Input:
 * - POST JSON { "url": "https://open.spotify.com/track/..." }
 * - ODER kein Input → aktuell laufender Track
 */

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$trackId = null;

/* ---------- Fall 1: Track-Link übergeben ---------- */
if (is_array($body) && !empty($body['url'])) {
    $input = urldecode(trim((string)$body['url']));

    if (preg_match('~open\.spotify\.com/(?:intl-[^/]+/)?track/([A-Za-z0-9]{22})~i', $input, $m)) {
        $trackId = $m[1];
    } elseif (preg_match('~spotify:track:([A-Za-z0-9]{22})~i', $input, $m)) {
        $trackId = $m[1];
    } else {
        json_fail(400, 'Ungültiger Spotify-Track-Link');
    }
}

/* ---------- Fall 2: kein Link → aktuell spielender Track ---------- */
if (!$trackId) {
    $player = spotify_api(
        'GET',
        'https://api.spotify.com/v1/me/player',
        $token,
        null,
        [200,204]
    );

    if ($player === 204 || empty($player['item']['id'])) {
        json_fail(400, 'Kein aktiver Spotify-Track');
    }

    $trackId = $player['item']['id'];
}

/* ---------- Track-Infos ---------- */
$track = spotify_api(
    'GET',
    "https://api.spotify.com/v1/tracks/{$trackId}",
    $token
);

$title   = $track['name'] ?? '';
$artists = array_map(fn($a) => $a['name'] ?? '', $track['artists'] ?? []);
$artistStr = implode(', ', array_filter($artists));

/* ---------- Zur Favoriten-Playlist hinzufügen ---------- */
$res = spotify_api(
    'POST',
    "https://api.spotify.com/v1/playlists/{$favPlaylistId}/tracks",
    $token,
    ['uris' => ["spotify:track:{$trackId}"]]
);

if (isset($res['error'])) {
    json_fail(500, $res['error']);
}

json_ok([
    'message' => "⭐ Favorit gespeichert: {$artistStr} — {$title}",
    'track_id' => $trackId
]);
