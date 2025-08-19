<?php
require __DIR__.'/bootstrap.php';
$playlistId = envv('SPOTIFY_PLAYLIST_ID');
$token = get_access_token_from_refresh();
$uris = [];
$pl = spotify_api('GET', "https://api.spotify.com/v1/playlists/{$playlistId}/tracks", $token);
foreach ($pl['items'] ?? [] as $it) {
    $u = $it['track']['uri'] ?? null;
    if ($u) $uris[] = $u;
}
if (!$uris) json_ok(['message'=>'Playlist war bereits leer']);
spotify_api('DELETE', "https://api.spotify.com/v1/playlists/{$playlistId}/tracks", $token, [
    'tracks' => array_map(fn($u)=>['uri'=>$u], $uris)
]);
json_ok(['message'=>'Playlist geleert','removed'=>count($uris)]);
