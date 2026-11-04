<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

send_security_headers();

$viewerToken = (string) ($_GET['token'] ?? '');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Live-Ziehung</title>
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 640px;">
  <?php require __DIR__ . '/partials/draw-display.php'; ?>
  <?php require __DIR__ . '/partials/footer.php'; ?>
</main>
<script src="assets/js/matrix-rain.js"></script>
<script src="assets/js/draw-view.js"></script>
</body>
</html>
