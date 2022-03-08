<?php
/**
 * Dom Parser
 */

namespace App\Html;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Parser
 *
 * @package App\Html
 */
class Parser
{
    /**
     * Return list of topics data
     *
     * @param string $html
     * @return array
     */
    public static function getTopicsData($html)
    {
        $data = self::getData($html);

        return array_map(function($topic) {
            return [
                'slug' => str_replace(LARACASTS_BASE_URL . '/topics/', '', $topic['path']),
                'path' => $topic['path'],
                'episode_count' => $topic['episode_count'],
                'series_count' => $topic['series_count']
            ];
        }, $data['props']['topics']);
    }

    /**
     * Return full list of series for given topic HTML page.
     *
     * @param string $html
     * @return array
     */
    public static function getSeriesData($html)
    {
        $data = self::getData($html);

        $series = $data['props']['topic']['series'];

        return array_combine(
            array_column($series, 'slug'),
            array_map(function($serie) {
                return [
                    'slug' => $serie['slug'],
                    'path' => LARACASTS_BASE_URL . $serie['path'],
                    'episode_count' => $serie['episodeCount'],
                    'is_complete' => $serie['complete']
                ];
            }, $series)
        );
    }

    /**
     * Return full list of episodes for given series HTML page.
     *
     * @param string $html
     * @return array
     */
    public static function getEpisodesData($html)
    {
        $data = self::getData($html);

        $link = $data['props']['downloadLink'];

        return [
            'title' => $data['props']['lesson']['title'],
            // Some video links starts with '//' and doesn't include protocol
            'download_link' => strpos($link, 'https:') === 0 ? $link : 'https:' . $link,
            'number' => $data['props']['lesson']['position']
        ];
    }

    public static function extractLarabitsSeries($html)
    {
        $html = str_replace('\/', '/', html_entity_decode($html));

        preg_match_all('"\/series\/([a-z-]+-larabits)"', $html, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Return larabits data
     *
     * @param string $html
     * @return array
     */
    public static function getLarabitsData($html)
    {
        $data = self::getData($html);

        return [
            'slug' => $data['props']['series']['slug'],
            'path' => LARACASTS_BASE_URL . $data['props']['series']['path'],
            'episode_count' => $data['props']['series']['episodeCount'],
            'is_complete' => $data['props']['series']['complete'],
        ];
    }

    public static function getCsrfToken($html)
    {
        preg_match('/"csrfToken": \'([^\s]+)\'/', $html, $matches);

        return $matches[1];
    }

    public static function getUserData($html)
    {

        $data = self::getData($html);

        $props = $data['props'];

        return [
            'error' => empty($props['errors']) ? null : $props['errors']['auth'],
            'signedIn' => $props['auth']['signedIn'],
            'data' => $props['auth']['user']
        ];
    }

    /**
     * Returns decoded version of data-page attribute in HTML page
     *
     * @param string $html
     * @return array
     */
    private static function getData($html)
    {
        $parser = new Crawler($html);

        $data = $parser->filter("#app")->attr('data-page');

        return json_decode($data, true);
    }
}
