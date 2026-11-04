<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

send_security_headers();
require_post();

check_rate_limit(
    'create',
    (int) APP_CONFIG['create_rate_limit_count'],
    (int) APP_CONFIG['create_rate_limit_window_s']
);

$body = read_body();

if (!TurnstileService::verify((string) ($body['turnstile_token'] ?? ''))) {
    json_error('Sicherheitsprüfung (Cloudflare Turnstile) fehlgeschlagen. Bitte erneut versuchen.', 400);
}

$title = trim((string) ($body['title'] ?? ''));
if ($title === '') {
    $title = 'Live-Ziehung';
}
if (mb_strlen($title) > 120) {
    $title = mb_substr($title, 0, 120);
}

$minAllowed = (int) APP_CONFIG['min_allowed'];
$maxAllowed = (int) APP_CONFIG['max_allowed'];

$min = filter_var($body['min'] ?? APP_CONFIG['default_min'], FILTER_VALIDATE_INT);
$max = filter_var($body['max'] ?? APP_CONFIG['default_max'], FILTER_VALIDATE_INT);

if ($min === false || $max === false) {
    json_error('Zahlenbereich muss aus ganzen Zahlen bestehen.');
}
if ($min < $minAllowed || $max > $maxAllowed || $min >= $max) {
    json_error('Ungültiger Zahlenbereich.');
}

$durationSeconds = filter_var($body['duration_s'] ?? (APP_CONFIG['default_duration_ms'] / 1000), FILTER_VALIDATE_FLOAT);
if ($durationSeconds === false) {
    json_error('Ungültige Animationsdauer.');
}
$durationMs = (int) round($durationSeconds * 1000);
$durationMs = max((int) APP_CONFIG['min_duration_ms'], min((int) APP_CONFIG['max_duration_ms'], $durationMs));

$expiryOptions = APP_CONFIG['expiry_options_hours'];
$expiryHours = filter_var($body['expires_in_hours'] ?? APP_CONFIG['default_expiry_hours'], FILTER_VALIDATE_INT);
if ($expiryHours === false || !in_array($expiryHours, $expiryOptions, true)) {
    json_error('Ungültige Verfallszeit.');
}

$result = JsonSessionStore::create([
    'title' => $title,
    'min' => $min,
    'max' => $max,
    'duration_ms' => $durationMs,
    'expires_in_s' => $expiryHours * 3600,
]);

json_response([
    'viewer_token' => $result['viewer_token'],
    'creator_token' => $result['creator_token'],
], 201);
