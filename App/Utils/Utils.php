<?php
/**
 * Utilities
 */

namespace App\Utils;

/**
 * Class Utils
 *
 * @package App\Utils
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
        if (php_sapi_name() == "cli") {
            return "\n";
        }

        return "<br>";
    }

    /**
     * Counts the episodes from the array.
     *
     * @param $array
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
     * @param $onlineListArray
     * @param $localListArray
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
     *
     * @param $text
     */
    public static function box($text)
    {
        echo self::newLine();
        echo "====================================" . self::newLine();
        echo $text . self::newLine();
        echo "====================================" . self::newLine();
    }

    /**
     * Echo's a message.
     *
     * @param $text
     */
    public static function write($text)
    {
        echo "> " . $text . self::newLine();
    }

    /**
     * Remove specials chars that windows does not support for filenames.
     *
     * @param $name
     * @return mixed
     */
    public static function parseEpisodeName($name)
    {
        return preg_replace('/[^A-Za-z0-9\- _]/', '', $name);
    }

    /**
     * Echo's a message in a new line.
     *
     * @param $text
     */
    public static function writeln($text)
    {
        echo self::newLine();
        echo "> " . $text . self::newLine();
    }

    /**
     * Convert bytes to precision
     *
     * @param $bytes
     * @param int $precision
     * @return string
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Calculate a percentage
     *
     * @param $cur
     * @param $total
     * @return float
     */
    public static function getPercentage($cur, $total)
    {
        return @($cur / $total * 100); //hide warning division by zero
    }
}
