<?php

/**
 * Usage:
 *   php twilio_message_status.php <MessageSid>
 *
 * Reads Twilio credentials from .env:
 *   EXCLUSIVA_TWILIO_ACCOUNT_SID / EXCLUSIVA_TWILIO_AUTH_TOKEN
 * (falls back to TWILIO_ACCOUNT_SID / TWILIO_AUTH_TOKEN)
 */

function readEnvValue(string $envPath, string $key): ?string
{
    if (!is_file($envPath)) {
        return null;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return null;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if ($k !== $key) {
            continue;
        }

        $v = trim($v);

        // Strip surrounding quotes if present.
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }

        // Remove any trailing inline comment (best-effort).
        // e.g. KEY=value # comment
        if (str_contains($v, ' #')) {
            $v = explode(' #', $v, 2)[0];
            $v = trim($v);
        }

        // Windows CR safety.
        $v = str_replace("\r", '', $v);

        return $v;
    }

    return null;
}

function envGet(string $envPath, array $keys): ?string
{
    foreach ($keys as $key) {
        $val = readEnvValue($envPath, $key);
        if ($val !== null && $val !== '') {
            return $val;
        }
    }
    return null;
}

function httpGetJson(string $url, string $user, string $pass): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Failed to init curl');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('curl error: ' . $err);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON response (HTTP ' . $code . '): ' . substr($body, 0, 500));
    }

    if ($code < 200 || $code >= 300) {
        $msg = $decoded['message'] ?? ('HTTP ' . $code);
        throw new RuntimeException('Twilio API error: ' . $msg);
    }

    return $decoded;
}

$messageSid = $argv[1] ?? null;
if (!$messageSid) {
    fwrite(STDERR, "Usage: php twilio_message_status.php <MessageSid>\n");
    exit(2);
}

$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';

$accountSid = envGet($envPath, ['EXCLUSIVA_TWILIO_ACCOUNT_SID', 'TWILIO_ACCOUNT_SID']);
$authToken = envGet($envPath, ['EXCLUSIVA_TWILIO_AUTH_TOKEN', 'TWILIO_AUTH_TOKEN']);

if (!$accountSid || !$authToken) {
    fwrite(STDERR, "Could not read Twilio credentials from .env (need EXCLUSIVA_TWILIO_ACCOUNT_SID and EXCLUSIVA_TWILIO_AUTH_TOKEN)\n");
    exit(2);
}

$url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages/' . rawurlencode($messageSid) . '.json';

try {
    $data = httpGetJson($url, $accountSid, $authToken);

    $out = [
        'message_sid' => $data['sid'] ?? $messageSid,
        'status' => $data['status'] ?? null,
        'error_code' => $data['error_code'] ?? null,
        'error_message' => $data['error_message'] ?? null,
        'to' => $data['to'] ?? null,
        'from' => $data['from'] ?? null,
        'date_created' => $data['date_created'] ?? null,
        'date_sent' => $data['date_sent'] ?? null,
        'date_updated' => $data['date_updated'] ?? null,
        'num_segments' => $data['num_segments'] ?? null,
        'price' => $data['price'] ?? null,
        'price_unit' => $data['price_unit'] ?? null,
    ];

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
