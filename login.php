<?php
require __DIR__ . '/bootstrap.php';

// welche App? per Parameter steuerbar
$app = $_GET['app'] ?? 'main';

if ($app === 'autoclear') {
    $clientId     = envv('SPOTIFY_CLIENT_ID_AUTOCLEAR');
    $clientSecret = envv('SPOTIFY_CLIENT_SECRET_AUTOCLEAR');
} else {
    $clientId     = envv('SPOTIFY_CLIENT_ID_MAIN');
    $clientSecret = envv('SPOTIFY_CLIENT_SECRET_MAIN');
}

$redirectUri = envv('SPOTIFY_REDIRECT_URI');

// Start Authorize Flow
$authUrl = "https://accounts.spotify.com/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id'     => $clientId,
    'scope'         => 'playlist-modify-public playlist-modify-private user-read-playback-state',
    'redirect_uri'  => $redirectUri,
    'state'         => $app // merken, welche App es war
]);

header("Location: $authUrl");
exit;