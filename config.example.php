<?php
/**
 * Konfigurationsvorlage. Datei zu config.php kopieren und anpassen.
 * config.php NICHT ins Webroot/öffentliche Verzeichnis legen, falls das Hosting
 * einen Ordner außerhalb des Webroots erlaubt.
 */

return [
    // Absoluter Pfad zum privaten Datenverzeichnis (JSON-/Lock-/Pointer-Dateien).
    // Falls möglich außerhalb des öffentlich erreichbaren Webroots platzieren.
    'private_dir' => __DIR__ . '/private/live-draw',

    // Erlaubte Werte für die Verfallszeit einer Sitzung (in Stunden), Admin/Ersteller wählt.
    'expiry_options_hours' => [1, 6, 24, 72, 168],
    'default_expiry_hours' => 24,

    // Grenzwerte für Zahlenbereich und Animationsdauer.
    'min_allowed' => -1000000,
    'max_allowed' => 1000000,
    'default_min' => 1,
    'default_max' => 100,
    'default_duration_ms' => 5000,
    'min_duration_ms' => 1500,
    'max_duration_ms' => 20000,

    // Vorlaufzeit zwischen Start-Request und Animationsbeginn (ms), damit Polling-Clients
    // die Startinformation sicher vor Beginn laden.
    'lead_time_ms' => 2500,

    // Einfaches Rate-Limit fürs öffentliche Erstellen neuer Sitzungen.
    'create_rate_limit_count' => 10,
    'create_rate_limit_window_s' => 600,

    // Wahrscheinlichkeit (0..1) und Mindestabstand (s) für die automatische Bereinigung
    // abgelaufener Sitzungen ohne Cronjob.
    'cleanup_probability' => 0.01,
    'cleanup_min_interval_s' => 60,

    // Cloudflare-Turnstile-Keys: siehe config.turnstile.example.php
    // (eigene Datei, nicht hier).
];
