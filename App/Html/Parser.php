<?php

/**
 * Dom Parser
 */

namespace App\Html;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Parser
 */
class Parser
{
    /**
     * Return list of topics data
     *
     * @param  string  $html
     */
    public static function getTopicsData($html): array
    {
        $data = self::getData($html);

        return array_map(fn ($topic): array => [
            'slug' => str_replace(LARACASTS_BASE_URL.'/topics/', '', $topic['path']),
            'path' => $topic['path'],
            'episode_count' => $topic['episode_count'],
            'series_count' => $topic['series_count'],
        ], $data['props']['topics']);
    }

    public static function getSerieData($serieHtml): array
    {
        $data = self::getData($serieHtml);

        return self::extractSerieData($data['props']['series']);
    }

    /**
     * Return full list of series for given topic HTML page.
     *
     * @param  string  $html
     */
    public static function getSeriesDataFromTopic($html): array
    {
        $data = self::getData($html);

        $series = $data['props']['topic']['series'];

        return array_combine(
            array_column($series, 'slug'),
            array_map(fn ($serie): array => self::extractSerieData($serie), $series)
        );
    }

    /**
     * Only extracts data we need for each serie and returns them
     */
    public static function extractSerieData(array $serie): array
    {
        return [
            'slug' => $serie['slug'],
            'path' => LARACASTS_BASE_URL.$serie['path'],
            'episode_count' => $serie['episodeCount'],
            'is_complete' => $serie['complete'],
        ];
    }

    /**
     * Return full list of episodes for given series HTML page.
     *
     * @param  string  $episodeHtml
     * @param  number[]  $filteredEpisodes
     */
    public static function getEpisodesData($episodeHtml, $filteredEpisodes = []): array
    {
        $episodes = [];

        $data = self::getData($episodeHtml);

        $chapters = $data['props']['series']['chapters'];

        foreach ($chapters as $chapter) {
            foreach ($chapter['episodes'] as $episode) {
                // TODO: It's not the parser responsibility to filter episodes
                if (! empty($filteredEpisodes) && ! in_array($episode['position'], $filteredEpisodes)) {
                    continue;
                }

                // vimeoId is null for upcoming episodes
                if (! $episode['vimeoId']) {
                    continue;
                }

                $episodes[] = [
                    'title' => $episode['title'],
                    'vimeo_id' => $episode['vimeoId'],
                    'number' => $episode['position'],
                ];
            }
        }

        return $episodes;
    }

    public static function getEpisodeDownloadLink($episodeHtml)
    {
        $data = self::getData($episodeHtml);

        return $data['props']['downloadLink'];
    }

    public static function extractLarabitsSeries($html): array
    {
        $html = str_replace('\/', '/', html_entity_decode((string) $html));

        preg_match_all('"\/series\/([a-z-]+-larabits)"', $html, $matches);

        return array_unique($matches[1]);
    }

    public static function getCsrfToken($html): string
    {
        preg_match('/"csrfToken": \'([^\s]+)\'/', (string) $html, $matches);

        return $matches[1];
    }

    public static function getUserData($html): array
    {

        $data = self::getData($html);

        $props = $data['props'];

        return [
            'error' => empty($props['errors']) ? null : $props['errors']['auth'],
            'signedIn' => $props['auth']['signedIn'],
            'data' => $props['auth']['user'],
        ];
    }

    /**
     * Returns decoded version of data-page attribute in HTML page
     *
     * @param  string  $html
     * @return array
     */
    private static function getData($html): mixed
    {
        $parser = new Crawler($html);

        $data = $parser->filter('#app')->attr('data-page');

        return json_decode((string) $data, true);
    }
}
