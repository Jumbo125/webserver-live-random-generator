<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

send_security_headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_error('Methode nicht erlaubt.', 405);
}

$token = (string) ($_GET['token'] ?? '');
$nowMs = now_ms();

if ($token === '') {
    json_response([
        'server_now_ms' => $nowMs,
        'session' => ['state' => 'invalid'],
        'round' => null,
    ]);
}

$session = JsonSessionStore::loadByViewerToken($token);

if ($session === null || ((int) $session['expires_at_ms']) <= $nowMs) {
    json_response([
        'server_now_ms' => $nowMs,
        'session' => ['state' => 'invalid'],
        'round' => null,
    ]);
}

$round = $session['current_round'];

json_response([
    'server_now_ms' => $nowMs,
    'session' => [
        'state' => 'ok',
        'title' => (string) $session['title'],
        'min' => (int) $session['min'],
        'max' => (int) $session['max'],
        'version' => (int) $session['version'],
    ],
    'round' => $round === null ? null : [
        'id' => (int) $round['id'],
        'result' => (int) $round['result'],
        'animation_seed' => (string) $round['animation_seed'],
        'start_at_ms' => (int) $round['start_at_ms'],
        'end_at_ms' => (int) $round['end_at_ms'],
        'duration_ms' => (int) $round['duration_ms'],
    ],
]);
