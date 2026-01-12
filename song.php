<?php
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$token = get_access_token_from_refresh();

/* ---------- Aktuell spielenden Track holen ---------- */
$player = spotify_api(
    'GET',
    'https://api.spotify.com/v1/me/player',
    $token,
    null,
    [200, 204]
);

if ($player === 204 || empty($player['item']['id'])) {
    json_fail(400, 'Kein aktiver Spotify-Track');
}

$trackId = $player['item']['id'];

/* ---------- Track-Infos ---------- */
$track = spotify_api(
    'GET',
    "https://api.spotify.com/v1/tracks/{$trackId}",
    $token
);

$title   = $track['name'] ?? '';
$artists = array_map(fn($a) => $a['name'] ?? '', $track['artists'] ?? []);
$artistStr = implode(', ', array_filter($artists));

json_ok([
    'message'  => "🎵 Aktueller Song: {$artistStr} — {$title}",
    'track_id'=> $trackId
]);
