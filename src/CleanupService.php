<?php
declare(strict_types=1);

/**
 * Entfernt abgelaufene Sitzungsdateien ohne Cronjob: wird bei einem kleinen
 * Prozentsatz der normalen API-Aufrufe probabilistisch mit angestoßen und
 * zusätzlich zeitlich gedrosselt (höchstens ein Lauf pro Minute).
 *
 * Bei wenig Zugriff auf die Anwendung kann die physische Löschung dadurch
 * erst beim nächsten passenden Request erfolgen; logisch sind abgelaufene
 * Sitzungen aber immer sofort gesperrt (siehe expires_at_ms-Prüfung in der API).
 */
final class CleanupService
{
    public static function maybeRun(): void
    {
        $probability = (float) APP_CONFIG['cleanup_probability'];
        if (mt_rand() / mt_getrandmax() > $probability) {
            return;
        }

        $markerPath = PRIVATE_DIR . '/.cleanup-marker';
        $minInterval = (int) APP_CONFIG['cleanup_min_interval_s'];
        $lastRun = is_file($markerPath) ? (int) @filemtime($markerPath) : 0;

        if (time() - $lastRun < $minInterval) {
            return;
        }

        // Marker sofort aktualisieren, damit parallele Requests nicht gleichzeitig aufräumen.
        @touch($markerPath);

        self::run();
    }

    public static function run(): void
    {
        $now = now_ms();

        foreach (glob(SESSIONS_DIR . '/*.json') ?: [] as $path) {
            $session = read_json_file($path);
            if ($session === null || !isset($session['expires_at_ms']) || ((int) $session['expires_at_ms']) <= $now) {
                $viewerHash = basename($path, '.json');
                @unlink($path);
                @unlink(JsonSessionStore::lockPath($viewerHash));
            }
        }

        foreach (glob(CREATORS_DIR . '/*.json') ?: [] as $path) {
            $pointer = read_json_file($path);
            if ($pointer === null || !isset($pointer['expires_at_ms']) || ((int) $pointer['expires_at_ms']) <= $now) {
                @unlink($path);
                continue;
            }
            // Verwaiste Pointer, deren Sitzung bereits anderweitig weg ist, ebenfalls entfernen.
            if (isset($pointer['viewer_token_hash']) && !is_file(JsonSessionStore::sessionPath((string) $pointer['viewer_token_hash']))) {
                @unlink($path);
            }
        }

        // Rate-Limit-Dateien: Inhalt ist ohnehin nur innerhalb des Zeitfensters
        // relevant (ältere Zeitstempel werden bei jedem Zugriff ausgefiltert,
        // siehe check_rate_limit()). Aus Datensparsamkeit werden Dateien, die
        // länger als dieses Fenster nicht mehr angefasst wurden, auch physisch
        // entfernt, statt beliebig lange als leere Hülle liegen zu bleiben.
        $rateLimitWindowS = (int) APP_CONFIG['create_rate_limit_window_s'];
        foreach (glob(RATELIMIT_DIR . '/*.json') ?: [] as $path) {
            $mtime = (int) @filemtime($path);
            if ($mtime === 0 || time() - $mtime > $rateLimitWindowS) {
                @unlink($path);
            }
        }
    }
}
