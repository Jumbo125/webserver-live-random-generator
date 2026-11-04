<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/JsonSessionStore.php';
require __DIR__ . '/../src/DrawService.php';
require __DIR__ . '/../src/CleanupService.php';
require __DIR__ . '/../src/Turnstile.php';

CleanupService::maybeRun();
