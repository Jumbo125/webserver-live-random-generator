<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/JsonSessionStore.php';

send_security_headers();

$creatorToken = (string) ($_GET['token'] ?? '');
$resolved = $creatorToken !== '' ? JsonSessionStore::loadByCreatorToken($creatorToken) : null;

$valid = $resolved !== null && ((int) $resolved['session']['expires_at_ms']) > now_ms();
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ziehung steuern</title>
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 640px;">

<?php if (!$valid): ?>
  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <p class="fs-4 mb-3">🔒 Dieser Steuerungs-Link ist ungültig oder abgelaufen.</p>
      <a href="index.php" class="btn btn-primary">Neue Ziehung erstellen</a>
    </div>
  </div>
<?php else:
  $session = $resolved['session'];
  $viewerToken = $resolved['viewer_token'];
  $expiresLabel = gmdate('d.m.Y H:i', (int) ($session['expires_at_ms'] / 1000)) . ' UTC';
?>
  <h1 class="h4 mb-3"><?= h((string) $session['title']) ?></h1>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <h2 class="h6 text-muted text-uppercase">Zuschauer-Link teilen</h2>
      <div class="input-group mb-3">
        <input type="text" class="form-control" id="viewer-url" readonly>
        <button class="btn btn-outline-secondary" type="button" id="copy-link-btn">Kopieren</button>
      </div>
      <div class="text-center mb-2">
        <div id="qr-container" class="d-inline-block p-2 bg-white rounded"></div>
      </div>
      <p class="text-muted small mb-0">
        Zahlenbereich: <?= (int) $session['min'] ?> – <?= (int) $session['max'] ?> ·
        Link gültig bis <?= h($expiresLabel) ?>
      </p>
    </div>
  </div>

  <div class="mb-4">
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="excluded-enabled" checked>
      <label class="form-check-label small text-muted" for="excluded-enabled">
        Bereits gezogene Zahlen ausschließen
      </label>
    </div>
    <input type="text" class="form-control mb-3" id="excluded-numbers" placeholder="z. B. 3;7;12 (getrennt mit ;)">
    <div class="d-grid gap-2">
      <button type="button" class="btn btn-primary btn-lg" id="start-btn">🎲 Zahl ziehen</button>
      <div id="start-error" class="alert alert-danger d-none" role="alert"></div>
    </div>
  </div>

  <?php require __DIR__ . '/partials/draw-display.php'; ?>

  <div class="text-center mt-4">
    <button type="button" class="btn btn-link text-danger" id="delete-btn">Sitzung jetzt löschen</button>
  </div>

  <script src="assets/vendor/qrcode.min.js"></script>
  <script src="assets/js/matrix-rain.js"></script>
  <script src="assets/js/draw-view.js"></script>
  <script>
    window.CONTROL_DATA = {
      creatorToken: <?= json_encode($creatorToken) ?>,
      viewerToken: <?= json_encode($viewerToken) ?>
    };
  </script>
  <script src="assets/js/control.js"></script>
<?php endif; ?>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</main>
</body>
</html>
