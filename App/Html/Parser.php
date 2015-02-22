<?php namespace App\Html;

use Symfony\Component\DomCrawler\Crawler;

class Parser
{

    /**
     * Parses the html and adds the lessons the the array
     *
     * @param $html
     * @param $array
     */
    public static function getAllLessons($html, &$array)
    {
        $parser = new Crawler($html);

        $parser->filter('a.js-lesson-title')->each(function (Crawler $node, $i) use (&$array) {
            $link = $node->attr('href');

            if (preg_match('/' . LARACASTS_LESSONS_PATH . '\/(.+)/', $link, $matches)) { // lesson
                $array['lessons'][] = $matches[1];
            }

            if (preg_match('/' . LARACASTS_SERIES_PATH . '\/(.+)\/episodes\/(\d+)/', $link, $matches)) { // lesson
                $array['series'][$matches[1]][] = (int)$matches[2];
            }
        });
    }

    /**
     * Determines if there is next page, false if not or the link
     *
     * @param $html
     *
     * @return bool|string
     */
    public static function hasNextPage($html)
    {
        $parser = new Crawler($html);

        $node = $parser->filter('[rel=next]');
        if ($node->count() > 0)
            return $node->attr('href');

        return FALSE;
    }

    /**
     * Gets the token input
     *
     * @param $html
     *
     * @return string
     */
    public static function getToken($html)
    {
        $parser = new Crawler($html);

        return $parser->filter("input[name=_token]")->attr('value');
    }

    /**
     * Gets the download link
     *
     * @param $html
     */
    public static function getDownloadLink($html)
    {
        preg_match('/(\/downloads\/\d+\?type=\w+)/', $html, $matches);

        return $matches[0];
    }

    /**
     * Extracts the name of the episode
     *
     * @param $html
     *
     * @return string
     */
    public static function getNameOfEpisode($html)
    {
        $parser = new Crawler($html);

        return trim($parser->filter('.lesson-title')->text());
    }
}