<?php
declare(strict_types=1);

function json_fail(int $code, $err) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    // Fehler normalisieren
    if (empty($err)) {
        $err = ["Unbekannter Fehler"];
    } elseif (!is_array($err)) {
        $err = [$err];
    }

    echo json_encode(
        ['ok'=>false,'error'=>$err],
        JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
    );
    exit;
}

function json_ok($data = []) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok'=>true] + (is_array($data)?$data:['data'=>$data]),
        JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
    );
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
    $script = basename($_SERVER['SCRIPT_NAME']);

    if ($script === 'autoclear.php') {
        $clientId     = envv('SPOTIFY_CLIENT_ID_AUTOCLEAR');
        $clientSecret = envv('SPOTIFY_CLIENT_SECRET_AUTOCLEAR');
        $refresh      = envv('SPOTIFY_REFRESH_TOKEN_AUTOCLEAR');
    } else {
        $clientId     = envv('SPOTIFY_CLIENT_ID_MAIN');
        $clientSecret = envv('SPOTIFY_CLIENT_SECRET_MAIN');
        $refresh      = envv('SPOTIFY_REFRESH_TOKEN_MAIN');
    }

    if ($refresh === '') {
        json_fail(401, 'REFRESH_TOKEN fehlt – erst login.php aufrufen');
    }

    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type'=>'refresh_token',
            'refresh_token'=>$refresh,
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
    $token = $data['access_token'] ?? '';
    if ($token === '') json_fail(401, 'Kein access_token');
    return $token;
}

function spotify_api($method, $url, $token, $body = null) {
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json',
    ];
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);

    if ($resp === false) {
        return ['error' => curl_error($ch)];
    }

    $data = json_decode($resp, true);

    if ($data === null) {
        return ['error' => 'Leere oder ungültige Antwort: '.$resp];
    }

    // ✅ nur dann Fehler, wenn Spotify "error" liefert
    if (isset($data['error'])) {
        return ['error' => $data['error']];
    }

    // ✅ snapshot_id & alle anderen Daten normal zurückgeben
    return $data;
}
