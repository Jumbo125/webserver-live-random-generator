<?php
declare(strict_types=1);

/**
 * Lädt Konfiguration, legt private Verzeichnisse an und stellt kleine Helper
 * bereit, die von index.php, control.php, live.php und den API-Endpoints
 * gemeinsam genutzt werden.
 */

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/../config.example.php';
}
/** @var array<string,mixed> $config */
$config = require $configFile;

// Cloudflare-Turnstile-Keys liegen bewusst in einer eigenen, per .gitignore
// ausgeschlossenen Datei, getrennt vom restlichen (unkritischeren) Setup.
$turnstileFile = __DIR__ . '/../config.turnstile.php';
if (!is_file($turnstileFile)) {
    $turnstileFile = __DIR__ . '/../config.turnstile.example.php';
}
/** @var array{site_key?:string,secret_key?:string} $turnstileConfig */
$turnstileConfig = require $turnstileFile;
$config['turnstile_site_key'] = trim((string) ($turnstileConfig['site_key'] ?? ''));
$config['turnstile_secret_key'] = trim((string) ($turnstileConfig['secret_key'] ?? ''));

define('APP_CONFIG', $config);

define('PRIVATE_DIR', rtrim((string)$config['private_dir'], '/\\'));
define('SESSIONS_DIR', PRIVATE_DIR . '/sessions');
define('CREATORS_DIR', PRIVATE_DIR . '/creators');
define('RATELIMIT_DIR', PRIVATE_DIR . '/ratelimit');

foreach ([PRIVATE_DIR, SESSIONS_DIR, CREATORS_DIR, RATELIMIT_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    @chmod($dir, 0700);
}

// .htaccess-Schutz sicherstellen, falls das private Verzeichnis versehentlich
// im Webroot liegt (z. B. weil das Hosting keinen Ordner außerhalb erlaubt).
$htaccess = PRIVATE_DIR . '/.htaccess';
if (!is_file($htaccess)) {
    file_put_contents($htaccess, "Require all denied\nDeny from all\n");
}

/**
 * Sicherheits-/Caching-Header, die auf allen Token- und API-Seiten gesetzt werden müssen.
 */
function send_security_headers(): void
{
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

function json_response(array $data, int $status = 200): never
{
    send_security_headers();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): never
{
    json_response(['error' => $message], $status);
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_error('Methode nicht erlaubt.', 405);
    }
}

/**
 * Liest den Request-Body als JSON oder liefert $_POST, falls kein JSON-Body vorliegt.
 *
 * @return array<string,mixed>
 */
function read_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function new_secret_token(): string
{
    return bin2hex(random_bytes(32));
}

function hash_token(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Sehr einfache, auf JSON-Dateien basierende Rate-Limitierung pro Client-IP.
 * Nicht kryptografisch robust (IPs können geteilt/gespooft werden), reicht aber
 * als einfacher Schutz gegen versehentlichen oder leicht automatisierten Missbrauch
 * des öffentlichen Erstellungs-Endpunkts.
 */
function check_rate_limit(string $bucket, int $maxCount, int $windowSeconds): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $bucket . '|' . $ip);
    $file = RATELIMIT_DIR . '/' . $key . '.json';

    $now = time();
    $entries = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $entries = $decoded;
        }
    }

    $entries = array_values(array_filter($entries, static fn ($ts) => is_int($ts) && $ts > $now - $windowSeconds));

    if (count($entries) >= $maxCount) {
        json_error('Zu viele Anfragen. Bitte kurz warten.', 429);
    }

    $entries[] = $now;
    atomic_write_json($file, $entries);
}

/**
 * Schreibt Daten als JSON über eine temporäre Datei + rename() atomar,
 * damit Leser niemals eine unvollständige Datei sehen.
 */
function atomic_write_json(string $path, mixed $data): void
{
    $dir = dirname($path);
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(8));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON-Encoding fehlgeschlagen.');
    }
    file_put_contents($tmp, $json);
    @chmod($tmp, 0600);
    rename($tmp, $path);
}

/**
 * Liest eine JSON-Datei; liefert null, wenn sie fehlt oder beschädigt ist.
 *
 * @return array<string,mixed>|null
 */
function read_json_file(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function now_ms(): int
{
    return (int) round(microtime(true) * 1000);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Parst eine Liste ganzer Zahlen aus einem Freitext (z. B. "3;7; 12,9"),
 * getrennt durch Semikolon, Komma oder Leerraum. Nicht-numerische Teile
 * werden stillschweigend ignoriert.
 *
 * @return int[]
 */
function parse_number_list(string $raw): array
{
    $parts = preg_split('/[;,\s]+/', trim($raw)) ?: [];
    $numbers = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (filter_var($part, FILTER_VALIDATE_INT) !== false) {
            $numbers[] = (int) $part;
        }
    }
    return array_values(array_unique($numbers));
}
