<?php

/**
 * Utilities
 */

namespace App\Utils;

use GuzzleHttp\Event\ProgressEvent;
use GuzzleHttp\Message\RequestInterface;

/**
 * Class Utils
 */
class Utils
{
    /**
     * New line supporting cli or browser.
     *
     * @return string
     */
    public static function newLine()
    {
        if (php_sapi_name() == 'cli') {
            return "\n";
        }

        return '<br>';
    }

    /**
     * Counts the episodes from the array.
     *
     * @return int
     */
    public static function countEpisodes($array)
    {
        $total = 0;

        foreach ($array as $serie) {
            $total += count($serie['episodes']);
        }

        return $total;
    }

    /**
     * Compare two arrays and returns the diff array.
     *
     * @return array
     */
    public static function compareLocalAndOnlineSeries($onlineListArray, $localListArray)
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
    public static function box($text)
    {
        echo self::newLine();
        echo '===================================='.self::newLine();
        echo $text.self::newLine();
        echo '===================================='.self::newLine();
    }

    /**
     * Echo's a message.
     */
    public static function write($text)
    {
        echo '> '.$text.self::newLine();
    }

    /**
     * Remove specials chars that windows does not support for filenames.
     *
     * @return mixed
     */
    public static function parseEpisodeName($name)
    {
        return preg_replace('/[^A-Za-z0-9\- _]/', '', (string) $name);
    }

    /**
     * Echo's a message in a new line.
     */
    public static function writeln($text)
    {
        echo self::newLine();
        echo '> '.$text.self::newLine();
    }

    /**
     * Convert bytes to precision
     *
     * @param  int  $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2)
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
    public static function getPercentage($cur, $total)
    {
        // Hide warning division by zero
        return round(@($cur / $total * 100));
    }

    /**
     * @param  RequestInterface  $request
     * @param  int  $downloadedBytes
     * @param  int|null  $totalBytes
     */
    public static function showProgressBar($request, $downloadedBytes, $totalBytes = null)
    {
        if (php_sapi_name() == 'cli') {
            $request->getEmitter()->on('progress', function (ProgressEvent $e) use ($downloadedBytes, $totalBytes): void {

                $totalBytes ??= $e->downloadSize;

                printf("> Downloaded %s of %s (%d%%)      \r",
                    Utils::formatBytes($e->downloaded + $downloadedBytes),
                    Utils::formatBytes($totalBytes),
                    Utils::getPercentage($e->downloaded + $downloadedBytes, $totalBytes)
                );
            });
        }
    }
}
