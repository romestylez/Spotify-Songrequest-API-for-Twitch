<?php
require __DIR__.'/bootstrap.php';
$code = $_GET['code'] ?? '';
if ($code === '') json_fail(400, 'code fehlt');
$clientId = envv('SPOTIFY_CLIENT_ID');
$clientSecret = envv('SPOTIFY_CLIENT_SECRET');
$redirect = envv('SPOTIFY_REDIRECT_URI');
$ch = curl_init('https://accounts.spotify.com/api/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'=>'authorization_code',
        'code'=>$code,
        'redirect_uri'=>$redirect,
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
if (preg_match('/^SPOTIFY_REFRESH_TOKEN=.*$/m', $env)) {
    $env = preg_replace('/^SPOTIFY_REFRESH_TOKEN=.*$/m', 'SPOTIFY_REFRESH_TOKEN='.$refresh, $env);
} else {
    $env .= (str_ends_with($env, "\n") ? '' : "\n") . "SPOTIFY_REFRESH_TOKEN={$refresh}\n";
}
file_put_contents($envPath, $env);
echo "✅ Refresh Token gespeichert.";
