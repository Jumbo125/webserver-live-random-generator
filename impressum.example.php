<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

send_security_headers();
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Impressum</title>
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 640px;">

  <h1 class="h4 mb-4">Impressum</h1>

  <p class="small text-muted">Angaben gemäß § 5 ECG und § 25 Mediengesetz (Österreich).</p>

  <p>
    Dein Name<br>
    Deine Straße Hausnummer<br>
    PLZ Ort<br>
    Land
  </p>

  <p class="small text-muted">
    Kontakt: <!-- optional: E-Mail-Adresse hier ergänzen -->
  </p>

  <h2 class="h6 mt-4">Zweck dieser Anwendung</h2>
  <p>
    Dieser Live-Zufallsgenerator ist ein frei nutzbares, nicht-kommerzielles
    Online-Tool für synchronisierte Zufallsziehungen (z. B. Verlosungen,
    Auslosungen). Die Nutzung ist kostenlos.
  </p>

  <h2 class="h6 mt-4">Entwicklung</h2>
  <p>
    Diese Anwendung wurde mit Unterstützung von Künstlicher Intelligenz (KI)
    entwickelt.
  </p>

  <h2 class="h6 mt-4">Quellcode</h2>
  <p>
    Der Quellcode dieses Projekts ist hier einsehbar:
    <a href="#">GitHub-Link hier einfügen</a>
  </p>

  <h2 class="h6 mt-4">Datenschutz / Drittanbieter</h2>
  <p>
    Diese Anwendung verwendet keine Cookies und kein Tracking. Beim Erstellen
    einer Ziehung kann <strong>Cloudflare Turnstile</strong> (Cloudflare,
    Inc.) eingesetzt werden, um Menschen von automatisierten Zugriffen (Bots)
    zu unterscheiden. Dabei werden technische Daten (z. B. IP-Adresse,
    Browser-Signale) an Cloudflare übermittelt und verarbeitet. Mehr dazu in
    der
    <a href="https://www.cloudflare.com/privacypolicy/" target="_blank" rel="noopener">Datenschutzerklärung von Cloudflare</a>.
  </p>
  <p>
    Beim Erstellen einer Ziehung wird deine IP-Adresse kurzzeitig verarbeitet,
    um automatisierten Missbrauch zu verhindern (berechtigtes Interesse gemäß
    Art. 6 Abs. 1 lit. f DSGVO). Dabei wird nicht die IP-Adresse selbst
    gespeichert, sondern nur ein nicht umkehrbarer Hash-Wert zusammen mit
    Zeitstempeln; diese Daten werden nach maximal 10 Minuten automatisch
    verworfen und nicht an Dritte weitergegeben.
  </p>

  <h2 class="h6 mt-4">Haftungsausschluss</h2>
  <p class="small text-muted">
    Für die Richtigkeit, Vollständigkeit und Aktualität der bereitgestellten
    Informationen und Ziehungsergebnisse wird keine Haftung übernommen.
  </p>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</main>
</body>
</html>
