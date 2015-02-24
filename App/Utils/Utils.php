<?php
/**
 * Utilities
 */
namespace App\Utils;

/**
 * Class Utils
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
     * Count the total lessons of an array of lessons & series.
     *
     * @param $array
     *
     * @return int
     */
    public static function countAllLessons($array)
    {
        $total = count($array['lessons']);
        $total += self::countEpisodes($array);

        return $total;
    }

    /**
     * Counts the lessons from the array.
     *
     * @param $array
     *
     * @return int
     */
    public static function countLessons($array)
    {
        return count($array['lessons']);
    }

    /**
     * Counts the episodes from the array.
     *
     * @param $array
     *
     * @return int
     */
    public static function countEpisodes($array)
    {
        $total = 0;
        foreach ($array['series'] as $serie) {
            $total += count($serie);
        }

        return $total;
    }

    /**
     * Compare two arrays and returns the diff array.
     *
     * @param $onlineListArray
     * @param $localListArray
     *
     * @return array
     */
    public static function resolveFaultyLessons($onlineListArray, $localListArray)
    {
        $array['series'] = [];
        $array['lessons'] = [];

        foreach ($onlineListArray['series'] as $serie => $episodes) {
            if (isset($localListArray['series'][$serie])) {
                if (count($episodes) == count($localListArray['series'][$serie])) {
                    continue;
                }

                foreach ($episodes as $episode) {
                    if (!in_array($episode, $localListArray['series'][$serie])) {
                        $array['series'][$serie][] = $episode;
                    }
                }
            } else {
                $array['series'][$serie] = $episodes;
            }
        }

        $array['lessons'] = array_diff($onlineListArray['lessons'], $localListArray['lessons']);

        return $array;
    }

    /**
     * Echo's text in a nice box.
     *
     * @param $text
     */
    public static function box($text)
    {
        echo self::newLine();
        echo "====================================".self::newLine();
        echo $text.self::newLine();
        echo "====================================".self::newLine();
    }

    /**
     * Echo's a message.
     *
     * @param $text
     */
    public static function write($text)
    {
        echo "> ".$text.self::newLine();
    }

    /**
     * Remove specials chars that windows does not support for filenames.
     *
     * @param $name
     *
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
        echo "> ".$text.self::newLine();
    }
}
