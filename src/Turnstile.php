<?php
declare(strict_types=1);

/**
 * Prüft Cloudflare Turnstile serverseitig gegen die siteverify-API.
 * Ist kein secret_key konfiguriert, gilt die Prüfung als deaktiviert (immer gültig),
 * damit die Anwendung ohne Turnstile-Setup funktionsfähig bleibt.
 */
final class TurnstileService
{
    public static function isEnabled(): bool
    {
        return trim((string) (APP_CONFIG['turnstile_secret_key'] ?? '')) !== '';
    }

    public static function verify(string $token): bool
    {
        if (!self::isEnabled()) {
            return true;
        }
        if ($token === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => (string) APP_CONFIG['turnstile_secret_key'],
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $raw = self::postForm('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);
        if ($raw === null) {
            return false;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) && ($decoded['success'] ?? false) === true;
    }

    private static function postForm(string $url, string $payload): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            return $response === false ? null : $response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        return $response === false ? null : $response;
    }
}
