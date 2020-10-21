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
        preg_match("(\"\/downloads\/.*?\")", $html, $matches);

        if(isset($matches[0]) === false) {
            throw new NoDownloadLinkException();
        }

        return LARACASTS_BASE_URL . substr($matches[0],1,-1);
    }

    /**
     * Determine if this episode is scheduled for the future.
     *
     * @param $html
     * @return boolean
     */
    public static function scheduledEpisode($html)
    {
        preg_match("(return to watch it (.*)\.)", $html, $matches);

        if (isset($matches[1])) {
            return strip_tags($matches[1]);
        }

        return false;
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
        $t = $parser->filter("h4 a[href='/".$path."']")->text();

        return trim($t);
    }

    public static function getSeriesArray($html)
    {
        $parser = new Crawler($html);

        $seriesNodes = $parser->filter(".card");

        $series = $seriesNodes->each(function(Crawler $crawler) {
            $slug = str_replace('/series/', '', $crawler->filter('.expanded-card-heading a')->attr('href'));
            $episode_count = (int) $crawler->filter('.expanded-card-meta-lessons a')->text();

            return [
                'slug' => $slug,
                'episode_count' => $episode_count,
            ];
        });

        return $series;
    }


    public static function getEpisodesCount($html)
    {
        $parser = new Crawler($html);

        return $parser->filter("ol li.episode-list-item")->count();
    }
}
