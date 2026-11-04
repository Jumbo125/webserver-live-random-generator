<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

send_security_headers();

$expiryOptions = APP_CONFIG['expiry_options_hours'];
$defaultExpiry = APP_CONFIG['default_expiry_hours'];
$turnstileSiteKey = trim((string) (APP_CONFIG['turnstile_site_key'] ?? ''));
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Live-Zufallsgenerator</title>
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<?php if ($turnstileSiteKey !== ''): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 640px;">
  <h1 class="h3 mb-3">Live-Zufallsgenerator</h1>
  <p class="text-muted">Erstelle eine Ziehung. Du erhältst sofort einen geheimen Steuerungs-Link für dich und einen Zuschauer-Link mit QR-Code, den du teilen kannst. Jeder, der den Zuschauer-Link öffnet, sieht dieselbe Ziehung live und synchron.</p>

  <form id="create-form" class="card shadow-sm">
    <div class="card-body">
      <div class="mb-3">
        <label for="title" class="form-label">Titel (optional)</label>
        <input type="text" class="form-control" id="title" name="title" maxlength="120" placeholder="z. B. Verlosung Tombola">
      </div>

      <div class="row">
        <div class="col-6 mb-3">
          <label for="min" class="form-label">Min</label>
          <input type="number" class="form-control" id="min" name="min" value="1" required>
        </div>
        <div class="col-6 mb-3">
          <label for="max" class="form-label">Max</label>
          <input type="number" class="form-control" id="max" name="max" value="100" required>
        </div>
      </div>

      <div class="mb-3">
        <label for="duration" class="form-label">Animationsdauer (Sekunden)</label>
        <input type="number" class="form-control" id="duration" name="duration" value="5" min="1.5" max="20" step="0.5" required>
      </div>

      <div class="mb-3">
        <label for="expires" class="form-label">Link gültig für</label>
        <select class="form-select" id="expires" name="expires">
          <?php foreach ($expiryOptions as $hours): ?>
            <option value="<?= (int) $hours ?>" <?= $hours === $defaultExpiry ? 'selected' : '' ?>>
              <?= $hours < 24 ? h($hours . ' Stunden') : h(($hours / 24) . ' Tag(e)') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($turnstileSiteKey !== ''): ?>
        <div class="cf-turnstile mb-3" data-sitekey="<?= h($turnstileSiteKey) ?>"></div>
      <?php endif; ?>

      <div id="create-error" class="alert alert-danger d-none" role="alert"></div>

      <button type="submit" class="btn btn-primary w-100" id="create-submit">
        Link &amp; QR-Code erstellen
      </button>
    </div>
  </form>
  <?php require __DIR__ . '/partials/footer.php'; ?>
</main>
<script src="assets/js/create.js"></script>
</body>
</html>
