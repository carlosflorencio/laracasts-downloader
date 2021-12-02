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

        $episodes = [];

        $chapters = $data['props']['series']['chapters'];

        foreach ($chapters as $chapter) {
            foreach ($chapter['episodes'] as $episode) {
                array_push($episodes, $episode);
            }
        }

        return array_filter(
            array_combine(
                array_column($episodes, 'position'),
                array_map(function($episode) {
                    // In case you don't have active subscription.
                    if (! array_key_exists('download', $episode))
                        return null;

                    return [
                        'title' => $episode['title'],
                        // Some video links starts with '//' and doesn't include protocol
                        'download_link' => strpos($episode['download'], 'https:') === 0
                            ? $episode['download']
                            : 'https:' . $episode['download'],
                        'number' => $episode['position']
                    ];
                }, $episodes)
            )
        );
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
