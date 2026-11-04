<?php
declare(strict_types=1);

/**
 * Führt die eigentliche Ziehung aus: Zufallszahl genau einmal serverseitig
 * ermitteln und als neue Runde in der Sitzung speichern.
 */
final class DrawService
{
    /**
     * Startet eine neue Runde für die Sitzung mit dem gegebenen viewer_hash.
     * Atomar und doppelstart-sicher durch JsonSessionStore::withLock().
     *
     * @param int[] $excluded Bereits "verbrauchte" Zahlen, die bei dieser Ziehung
     *                        nicht mehr vorkommen dürfen (z. B. frühere Ergebnisse).
     * @throws RuntimeException Falls Sitzung abgelaufen ist, bereits eine Runde
     *                           läuft, oder keine Zahl mehr übrig ist.
     * @return array<string,mixed> Die aktualisierte Sitzung.
     */
    public static function startRound(string $viewerHash, int $leadTimeMs, array $excluded = []): array
    {
        return JsonSessionStore::withLock($viewerHash, function (array $session) use ($leadTimeMs, $excluded): array {
            $now = now_ms();

            if (((int) $session['expires_at_ms']) <= $now) {
                throw new RuntimeException('expired');
            }

            $current = $session['current_round'];
            if (is_array($current) && $now < ((int) $current['end_at_ms'])) {
                throw new RuntimeException('round_active');
            }

            $min = (int) $session['min'];
            $max = (int) $session['max'];
            $durationMs = (int) $session['duration_ms'];

            $validExcluded = array_values(array_unique(array_filter(
                $excluded,
                static fn (int $n): bool => $n >= $min && $n <= $max
            )));
            sort($validExcluded);

            $rangeSize = $max - $min + 1;
            if (count($validExcluded) >= $rangeSize) {
                throw new RuntimeException('no_numbers_left');
            }

            $result = self::pickExcludingSorted($min, $rangeSize, $validExcluded);

            $startAtMs = $now + $leadTimeMs;
            $endAtMs = $startAtMs + $durationMs;

            $round = [
                'id' => (int) $session['next_round_id'],
                'result' => $result,
                'excluded' => $validExcluded,
                'animation_seed' => bin2hex(random_bytes(8)),
                'start_at_ms' => $startAtMs,
                'end_at_ms' => $endAtMs,
                'duration_ms' => $durationMs,
            ];

            $session['current_round'] = $round;
            $session['next_round_id'] = $round['id'] + 1;

            return $session;
        });
    }

    /**
     * Wählt gleichverteilt eine Zahl aus [min, min+rangeSize-1] abzüglich der
     * (aufsteigend sortierten) ausgeschlossenen Zahlen. Läuft in O(count($sortedExcluded))
     * statt die gesamte Spanne aufzuzählen, damit auch sehr große Zahlenbereiche
     * mit wenigen Ausschlüssen effizient bleiben.
     *
     * @param int[] $sortedExcluded aufsteigend sortiert, bereits auf [min, min+rangeSize-1] begrenzt.
     */
    private static function pickExcludingSorted(int $min, int $rangeSize, array $sortedExcluded): int
    {
        $remaining = $rangeSize - count($sortedExcluded);
        $k = random_int(0, $remaining - 1);

        $candidate = $min + $k;
        foreach ($sortedExcluded as $excludedValue) {
            if ($excludedValue <= $candidate) {
                $candidate++;
            }
        }

        return $candidate;
    }
}
