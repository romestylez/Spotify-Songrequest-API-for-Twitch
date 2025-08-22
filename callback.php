<?php
require __DIR__.'/bootstrap.php';

$code  = $_GET['code']  ?? '';
$app   = $_GET['state'] ?? 'main'; // merken, welche App es war

if ($code === '') {
    json_fail(400, 'code fehlt');
}

// Richtige App auswählen
if ($app === 'autoclear') {
    $clientId     = envv('SPOTIFY_CLIENT_ID_AUTOCLEAR');
    $clientSecret = envv('SPOTIFY_CLIENT_SECRET_AUTOCLEAR');
    $tokenVar     = 'SPOTIFY_REFRESH_TOKEN_AUTOCLEAR';
} else {
    $clientId     = envv('SPOTIFY_CLIENT_ID_MAIN');
    $clientSecret = envv('SPOTIFY_CLIENT_SECRET_MAIN');
    $tokenVar     = 'SPOTIFY_REFRESH_TOKEN_MAIN';
}

$redirect = envv('SPOTIFY_REDIRECT_URI');

$ch = curl_init('https://accounts.spotify.com/api/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $redirect,
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret),
        'Content-Type: application/x-www-form-urlencoded'
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$resp = curl_exec($ch);
if ($resp === false) json_fail(502, curl_error($ch));
$data = json_decode($resp, true) ?: [];

$refresh = $data['refresh_token'] ?? '';
if ($refresh === '') json_fail(500, 'Kein refresh_token erhalten');

$envPath = __DIR__.'/.env';
$env = file_exists($envPath) ? file_get_contents($envPath) : '';

if (preg_match("/^{$tokenVar}=.*$/m", $env)) {
    $env = preg_replace("/^{$tokenVar}=.*$/m", "{$tokenVar}={$refresh}", $env);
} else {
    $env .= (str_ends_with($env, "\n") ? '' : "\n") . "{$tokenVar}={$refresh}\n";
}

file_put_contents($envPath, $env);

echo "✅ Refresh Token gespeichert für {$tokenVar}.";
