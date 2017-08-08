<?php
/**
 * Dom Parser
 */
namespace App\Html;

use App\Exceptions\NoDownloadLinkException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Parser
 * @package App\Html
 */
class Parser
{
    /**
     * Parses the html and adds the lessons the the array.
     *
     * @param $html
     * @param $array
     */
    public static function getAllLessons($html, &$array)
    {
        $parser = new Crawler($html);

        $parser->filter('h5.lesson-list-title')->each(function (Crawler $node) use (&$array) {
            $link = $node->children()->attr('href');
            if (preg_match('/'.LARACASTS_LESSONS_PATH.'\/(.+)/', $link, $matches)) { // lesson
                $array['lessons'][] = $matches[1];
            } else if ($node->children()->count() > 0) {
                $link = $node->children()->eq(0)->attr('href');

                if (preg_match('/'.LARACASTS_SERIES_PATH.'\/(.+)\/episodes\/(\d+)/', $link, $matches)) { // serie lesson
                    $array['series'][$matches[1]][] = (int) $matches[2];
                }
            }
        });
    }

    /**
     * Determines if there is next page, false if not or the link.
     *
     * @param $html
     *
     * @return bool|string
     */
    public static function hasNextPage($html)
    {
        $parser = new Crawler($html);

        $node = $parser->filter('[rel=next]');
        if ($node->count() > 0) {
            return $node->attr('href');
        }

        return false;
    }

    /**
     * Gets the token input.
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
     * Gets the download link.
     *
     * @param $html
     * @return string
     * @throws NoDownloadLinkException
     */
    public static function getDownloadLink($html)
    {
        preg_match('"\/downloads\/.*?")', $html, $matches);

        if(isset($matches[0]) === false) {
            throw new NoDownloadLinkException();
        }

        return LARACASTS_BASE_URL . substr($matches[0],1,-1);
    }

    /**
     * Extracts the name of the episode.
     *
     * @param $html
     *
     * @param $path
     * @return string
     */
    public static function getNameOfEpisode($html, $path)
    {
        $parser = new Crawler($html);
        $t = $parser->filter("a[href='/".$path."']")->text();

        return trim($t);
    }
}
