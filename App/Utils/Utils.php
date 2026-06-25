<?php

/**
 * Utilities
 */

namespace App\Utils;

/**
 * Class Utils
 */
class Utils
{
    /**
     * New line supporting cli or browser.
     */
    public static function newLine(): string
    {
        if (php_sapi_name() == 'cli') {
            return "\n";
        }

        return '<br>';
    }

    /**
     * Counts the episodes from the array.
     */
    public static function countEpisodes($array): int
    {
        $total = 0;

        foreach ($array as $serie) {
            $total += count($serie['episodes']);
        }

        return $total;
    }

    /**
     * Compare two arrays and returns the diff array.
     */
    public static function compareLocalAndOnlineSeries($onlineListArray, array $localListArray): array
    {
        $seriesCollection = new SeriesCollection([]);

        foreach ($onlineListArray as $serieSlug => $serie) {

            if (array_key_exists($serieSlug, $localListArray)) {
                if ($serie['episode_count'] == count($localListArray[$serieSlug])) {
                    continue;
                }

                $episodes = $serie['episodes'];
                $serie['episodes'] = [];

                foreach ($episodes as $episode) {
                    if (! in_array($episode['number'], $localListArray[$serieSlug])) {
                        $serie['episodes'][] = $episode;
                    }
                }

                $seriesCollection->add($serie);
            } else {
                $seriesCollection->add($serie);
            }
        }

        return $seriesCollection->get();
    }

    /**
     * Echo's text in a nice box.
     */
    public static function box(string $text): void
    {
        echo self::newLine();
        echo '===================================='.self::newLine();
        echo $text.self::newLine();
        echo '===================================='.self::newLine();
    }

    /**
     * Echo's a message.
     */
    public static function write(string $text): void
    {
        echo '> '.$text.self::newLine();
    }

    /**
     * Make the lesson title safe for a windows filename while keeping it as
     * close to the original as possible: characters windows forbids are
     * substituted with readable equivalents (": " -> " - ", '/'/'\\' -> '-',
     * '"' -> "'") where one exists, and only the truly unrepresentable ones
     * ('*', '?', '<', '>', '|') are dropped. Legal punctuation (',', '+',
     * '(', '!', '.', "'", ...) is preserved.
     */
    public static function parseEpisodeName(string $name): ?string
    {
        // strip control characters outright
        $name = preg_replace('/[\x00-\x1F]/', '', $name);

        if ($name === null) {
            return null;
        }

        // windows-illegal characters that have a sensible readable equivalent
        $name = strtr($name, [
            ':' => ' -',
            '/' => '-',
            '\\' => '-',
            '"' => "'",
        ]);

        // ... and the ones that don't -> drop
        $name = str_replace(['*', '?', '<', '>', '|'], '', $name);

        // collapse any double spaces a substitution may have introduced
        $name = preg_replace('/ {2,}/', ' ', $name) ?? $name;

        // windows also rejects trailing dots and spaces
        return rtrim($name, ' .');
    }

    /**
     * Readable series-title fallback derived from a slug
     * (e.g. 'advanced-eloquent' -> 'Advanced Eloquent').
     */
    public static function humanizeSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * Echo's a message in a new line.
     */
    public static function writeln(string $text): void
    {
        echo self::newLine();
        echo '> '.$text.self::newLine();
    }

    /**
     * Convert bytes to precision
     */
    public static function formatBytes($bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }

    /**
     * Calculate a percentage
     *
     * @return float
     */
    public static function getPercentage($cur, $total): int|float
    {
        // Hide warning division by zero
        if ($total === 0) {
            return 0;
        }

        return round(@($cur / $total * 100));
    }

    public static function showProgressBar(int $downloadedBytes, ?int $totalBytes = null): void
    {
        if (php_sapi_name() == 'cli') {
            printf("> Downloaded %s of %s (%d%%)      \r",
                Utils::formatBytes($downloadedBytes),
                Utils::formatBytes($totalBytes),
                Utils::getPercentage($downloadedBytes, $totalBytes)
            );
        }
    }
}
