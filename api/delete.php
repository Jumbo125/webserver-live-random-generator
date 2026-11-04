<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

send_security_headers();
require_post();

$body = read_body();
$creatorToken = (string) ($body['creator_token'] ?? '');

if ($creatorToken === '' || !JsonSessionStore::deleteByCreatorToken($creatorToken)) {
    json_error('Sitzung nicht gefunden.', 404);
}

json_response(['deleted' => true]);
