<?php
declare(strict_types=1);
function json_fail(int $code, $err) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>$err], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ok($data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true] + (is_array($data)?$data:['data'=>$data]), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
$envPath = __DIR__.'/.env';
if (!file_exists($envPath)) json_fail(500, '.env fehlt');
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line === '' || $line[0] === '#') continue;
    [$k,$v] = array_pad(explode('=', $line, 2), 2, '');
    $_ENV[trim($k)] = $v;
}
function envv(string $k, ?string $def=null): string {
    $v = $_ENV[$k] ?? $def;
    if ($v === null) json_fail(500, "Env $k fehlt");
    return $v;
}
function get_access_token_from_refresh(): string {
    $clientId = envv('SPOTIFY_CLIENT_ID');
    $clientSecret = envv('SPOTIFY_CLIENT_SECRET');
    $refresh = envv('SPOTIFY_REFRESH_TOKEN');
    if ($refresh === '') json_fail(401, 'REFRESH_TOKEN fehlt – erst login.php aufrufen');
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type'=>'refresh_token',
            'refresh_token'=>$refresh
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic '.base64_encode($clientId.':'.$clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) json_fail(502, curl_error($ch));
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true) ?: [];
    if ($code >= 300) json_fail($code, $data);
    return $data['access_token'] ?? '';
}
function spotify_api(string $method, string $url, string $accessToken, ?array $json = null): array {
    $ch = curl_init($url);
    $headers = ['Authorization: Bearer '.$accessToken, 'Content-Type: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    $resp = curl_exec($ch);
    if ($resp === false) json_fail(502, curl_error($ch));
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = $resp !== '' ? (json_decode($resp, true) ?: []) : [];
    if ($code >= 300) json_fail($code, $data);
    return $data;
}
function extract_track_id(string $url): ?string {
    if (preg_match('~(?:open\.spotify\.com/track/|spotify:track:)([A-Za-z0-9]+)~', $url, $m)) return $m[1];
    return null;
}
