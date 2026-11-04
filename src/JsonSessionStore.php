<?php
declare(strict_types=1);

/**
 * Speichert Sitzungen als einzelne JSON-Dateien im privaten Verzeichnis.
 *
 * Es gibt zwei Geheimnisse pro Sitzung:
 * - viewer_token: öffentlicher Zuschauer-Link (live.php) + QR-Code.
 * - creator_token: geheimer Ersteller-Link (control.php), mit dem Runden
 *   gestartet und die Sitzung gelöscht werden kann. Ersetzt Login/Passwort.
 *
 * Dateinamen werden ausschließlich aus sha256() der jeweiligen Tokens gebildet
 * (nie aus Nutzereingaben), sodass keine Pfad-Traversal möglich ist:
 *   SESSIONS_DIR/<sha256(viewer_token)>.json   Sitzungsdaten
 *   SESSIONS_DIR/<sha256(viewer_token)>.lock   Lockdatei für flock()
 *   CREATORS_DIR/<sha256(creator_token)>.json  Pointer auf den viewer_token
 *
 * Der rohe viewer_token wird nie in der Sitzungsdatei selbst gespeichert,
 * sondern nur (verschlüsselt durch Dateizugriffsschutz) in der Creator-Pointer-
 * Datei, da nur der Ersteller ihn zum Anzeigen von Link/QR benötigt.
 */
final class JsonSessionStore
{
    public static function sessionPath(string $viewerHash): string
    {
        return SESSIONS_DIR . '/' . $viewerHash . '.json';
    }

    public static function lockPath(string $viewerHash): string
    {
        return SESSIONS_DIR . '/' . $viewerHash . '.lock';
    }

    public static function creatorPath(string $creatorHash): string
    {
        return CREATORS_DIR . '/' . $creatorHash . '.json';
    }

    /**
     * Legt eine neue Sitzung an und gibt die rohen Tokens zurück (nur jetzt verfügbar).
     *
     * @param array{title:string,min:int,max:int,duration_ms:int,expires_in_s:int} $opts
     * @return array{viewer_token:string,creator_token:string,session:array<string,mixed>}
     */
    public static function create(array $opts): array
    {
        $now = now_ms();

        do {
            $viewerToken = new_secret_token();
            $viewerHash = hash_token($viewerToken);
            $path = self::sessionPath($viewerHash);
        } while (is_file($path));

        $creatorToken = new_secret_token();
        $creatorHash = hash_token($creatorToken);

        $session = [
            'token_hash' => $viewerHash,
            'title' => $opts['title'],
            'min' => $opts['min'],
            'max' => $opts['max'],
            'duration_ms' => $opts['duration_ms'],
            'created_at_ms' => $now,
            'expires_at_ms' => $now + ($opts['expires_in_s'] * 1000),
            'version' => 1,
            'next_round_id' => 1,
            'current_round' => null,
        ];

        atomic_write_json($path, $session);
        @chmod($path, 0600);

        $pointer = [
            'viewer_token' => $viewerToken,
            'viewer_token_hash' => $viewerHash,
            'created_at_ms' => $now,
            'expires_at_ms' => $session['expires_at_ms'],
        ];
        atomic_write_json(self::creatorPath($creatorHash), $pointer);
        @chmod(self::creatorPath($creatorHash), 0600);

        return [
            'viewer_token' => $viewerToken,
            'creator_token' => $creatorToken,
            'session' => $session,
        ];
    }

    /**
     * @return array<string,mixed>|null Rohe Sitzungsdaten (auch falls abgelaufen) oder null, falls unbekannt/beschädigt.
     */
    public static function loadByViewerToken(string $viewerToken): ?array
    {
        if ($viewerToken === '') {
            return null;
        }
        return read_json_file(self::sessionPath(hash_token($viewerToken)));
    }

    /**
     * Löst den Ersteller-Token auf und liefert den zugehörigen viewer_token plus
     * (falls noch vorhanden) die aktuelle Sitzung. Verwaiste Pointer (Sitzung bereits
     * durch Cleanup entfernt) werden dabei gleich mitentfernt.
     *
     * @return array{viewer_token:string,viewer_hash:string,session:array<string,mixed>|null}|null
     */
    public static function loadByCreatorToken(string $creatorToken): ?array
    {
        if ($creatorToken === '') {
            return null;
        }
        $creatorHash = hash_token($creatorToken);
        $pointerPath = self::creatorPath($creatorHash);
        $pointer = read_json_file($pointerPath);
        if ($pointer === null || !isset($pointer['viewer_token'], $pointer['viewer_token_hash'])) {
            return null;
        }

        $session = read_json_file(self::sessionPath((string) $pointer['viewer_token_hash']));
        if ($session === null) {
            // Sitzung wurde bereits bereinigt/gelöscht: verwaisten Pointer ebenfalls entfernen.
            @unlink($pointerPath);
            return null;
        }

        return [
            'viewer_token' => (string) $pointer['viewer_token'],
            'viewer_hash' => (string) $pointer['viewer_token_hash'],
            'session' => $session,
        ];
    }

    /**
     * Führt $mutator unter einer exklusiven Dateisperre für diese Sitzung aus.
     * $mutator bekommt die aktuelle Sitzung und muss die neue Sitzung zurückgeben.
     * Die Sperre liegt auf einer separaten .lock-Datei, damit das atomare
     * rename() der .json-Datei die Sperre nicht aushebelt.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $mutator
     * @return array<string,mixed> Die neue, gespeicherte Sitzung.
     */
    public static function withLock(string $viewerHash, callable $mutator): array
    {
        $lockPath = self::lockPath($viewerHash);
        $lockHandle = fopen($lockPath, 'c');
        if ($lockHandle === false) {
            throw new RuntimeException('Sperrdatei konnte nicht geöffnet werden.');
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Sitzung ist gerade gesperrt, bitte erneut versuchen.');
            }

            $sessionPath = self::sessionPath($viewerHash);
            $current = read_json_file($sessionPath);
            if ($current === null) {
                throw new RuntimeException('Sitzung nicht gefunden.');
            }

            $updated = $mutator($current);
            $updated['version'] = ((int) $current['version']) + 1;

            atomic_write_json($sessionPath, $updated);
            @chmod($sessionPath, 0600);

            return $updated;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /**
     * Löscht eine Sitzung sofort (Ersteller-Aktion). Entfernt Sitzungs-, Lock- und
     * Creator-Pointer-Datei.
     */
    public static function deleteByCreatorToken(string $creatorToken): bool
    {
        $creatorHash = hash_token($creatorToken);
        $pointerPath = self::creatorPath($creatorHash);
        $pointer = read_json_file($pointerPath);
        if ($pointer === null || !isset($pointer['viewer_token_hash'])) {
            return false;
        }

        $viewerHash = (string) $pointer['viewer_token_hash'];
        $lockPath = self::lockPath($viewerHash);
        $lockHandle = @fopen($lockPath, 'c');
        if ($lockHandle !== false) {
            flock($lockHandle, LOCK_EX);
        }

        @unlink(self::sessionPath($viewerHash));
        @unlink($pointerPath);

        if ($lockHandle !== false) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
        @unlink($lockPath);

        return true;
    }
}
