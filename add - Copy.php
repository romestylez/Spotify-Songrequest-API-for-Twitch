<?php
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$playlistId = envv('SPOTIFY_PLAYLIST_ID');

// --- Input holen: POST(JSON:{url}) oder GET (?url= / ?rawInput=)
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (is_array($body) && isset($body['url']) && is_string($body['url'])) {
    $input = $body['url'];
} else {
    $input = $_GET['url'] ?? $_GET['rawInput'] ?? '';
}
$input = trim((string)$input);
if ($input === '') {
    json_fail(400, 'Fehlender Parameter: url');
}

// ggf. URL-decodieren (kommt bei GET/Env-Vars vor)
$input = urldecode($input);

// --- Ersten gültigen TRACK-Link extrahieren (intl-xx support)
if (preg_match('~(?:https?://)?open\.spotify\.com/(?:intl-[^/]+/)?track/([A-Za-z0-9]{22})~i', $input, $m)) {
    $trackId = $m[1];
} elseif (preg_match('~spotify:track:([A-Za-z0-9]{22})~i', $input, $m)) {
    $trackId = $m[1];
} else {
    json_fail(400, 'Ungültiger Spotify-Track-Link');
}

// --- Token & Trackinfos holen
$token = get_access_token_from_refresh();
$track = spotify_api('GET', "https://api.spotify.com/v1/tracks/{$trackId}", $token);

$title   = $track['name'] ?? '';
$artists = array_map(fn($a) => $a['name'] ?? '', $track['artists'] ?? []);
$artistStr = implode(', ', array_filter($artists));

// --- Track ans Ende der Playlist hängen
spotify_api(
    'POST',
    "https://api.spotify.com/v1/playlists/{$playlistId}/tracks",
    $token,
    ['uris' => ["spotify:track:{$trackId}"]]
);

// --- Schöne Chat-Antwort zurückgeben
$msg = ($title || $artistStr)
    ? "🎵 Hinzugefügt: {$artistStr} — {$title}"
    : "Track hinzugefügt";

json_ok([
    'message'  => $msg,
    'track_id' => $trackId,
    'title'    => $title,
    'artists'  => $artists,
]);
