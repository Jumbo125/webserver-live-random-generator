<?php
/**
 * Enthält ausschließlich die Cloudflare-Turnstile-Zugangsdaten, getrennt von
 * config.php, damit dieses eine Geheimnis separat verwaltet und per
 * .gitignore vom restlichen (unkritischeren) Setup ausgeschlossen werden kann.
 *
 * Datei zu config.turnstile.php kopieren und mit den echten Keys aus dem
 * Cloudflare-Dashboard (Turnstile) befüllen. Beide Werte leer lassen, um
 * Turnstile komplett zu deaktivieren (kein Widget, keine Prüfung beim
 * Erstellen einer Sitzung).
 */

return [
    'site_key' => '',
    'secret_key' => '',
];
