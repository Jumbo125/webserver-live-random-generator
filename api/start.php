<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

send_security_headers();
require_post();

$body = read_body();
$creatorToken = (string) ($body['creator_token'] ?? '');
$excluded = parse_number_list((string) ($body['excluded'] ?? ''));

$resolved = JsonSessionStore::loadByCreatorToken($creatorToken);
if ($resolved === null) {
    json_error('Sitzung nicht gefunden oder abgelaufen.', 404);
}

try {
    $session = DrawService::startRound($resolved['viewer_hash'], (int) APP_CONFIG['lead_time_ms'], $excluded);
} catch (RuntimeException $e) {
    $message = match ($e->getMessage()) {
        'expired' => 'Sitzung ist abgelaufen.',
        'round_active' => 'Es läuft bereits eine Runde.',
        'no_numbers_left' => 'Alle Zahlen im Bereich sind bereits ausgeschlossen.',
        default => 'Runde konnte nicht gestartet werden.',
    };
    $status = match ($e->getMessage()) {
        'expired' => 410,
        'round_active' => 409,
        'no_numbers_left' => 409,
        default => 500,
    };
    json_error($message, $status);
}

$round = $session['current_round'];

json_response([
    'server_now_ms' => now_ms(),
    'round' => [
        'id' => (int) $round['id'],
        'result' => (int) $round['result'],
        'animation_seed' => (string) $round['animation_seed'],
        'start_at_ms' => (int) $round['start_at_ms'],
        'end_at_ms' => (int) $round['end_at_ms'],
        'duration_ms' => (int) $round['duration_ms'],
    ],
]);
