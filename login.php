<?php
require __DIR__.'/bootstrap.php';
$clientId = envv('SPOTIFY_CLIENT_ID');
$redirect = urlencode(envv('SPOTIFY_REDIRECT_URI'));
$scope = urlencode('playlist-modify-private playlist-modify-public user-read-playback-state');
$state = bin2hex(random_bytes(8));
$authUrl = "https://accounts.spotify.com/authorize?response_type=code&client_id={$clientId}&redirect_uri={$redirect}&scope={$scope}&state={$state}";
header('Content-Type: text/html; charset=utf-8');
echo "<a href=\"{$authUrl}\">Bei Spotify einloggen</a>";
