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
        preg_match("('\/downloads\/.*?')", $html, $matches);

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
        $t = $parser->filter("a[href='/".$path."'] h6")->text();

        return trim($t);
    }
}
