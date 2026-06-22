<?php

/**
 * Dom Parser
 */

namespace App\Html;

use Exception;
use Symfony\Component\DomCrawler\Crawler;

class Parser
{
    public static function getSerieData(string $serieHtml): array
    {
        $data = self::getData($serieHtml);

        return self::mapSerieData($data['props']['series']);
    }

    public static function mapSerieData(array $serie): array
    {
        return [
            'slug' => $serie['slug'],
            'title' => $serie['title'] ?? null,
            'path' => LARACASTS_BASE_URL.$serie['path'],
            'episode_count' => $serie['episodeCount'],
            'is_complete' => $serie['complete'],
        ];
    }

    /**
     * Return full list of episodes for given series HTML page.
     *
     * @param  number[]  $filteredEpisodes
     */
    public static function getEpisodesData(string $episodeHtml, $filteredEpisodes = []): array
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

                // no playback data on any host (mux/vimeo/cloudflare) means the
                // episode is upcoming and not yet downloadable
                if (empty($episode['muxPlaybackId'])
                    && empty($episode['vimeoId'])
                    && empty($episode['cloudflarePlayback']['src'])) {
                    continue;
                }

                $episodes[] = [
                    'title' => $episode['title'],
                    'vimeo_id' => $episode['vimeoId'] ?? null,
                    'number' => $episode['position'],
                    'published' => $episode['dateSegments']['published'] ?? null,
                ];
            }
        }

        return $episodes;
    }

    public static function getEpisodeDownloadLink(string $episodeHtml)
    {
        $data = self::getData($episodeHtml);

        return $data['props']['downloadLink'];
    }

    /**
     * Returns the Mux playback id and short-lived signed playback token
     * for the current episode page.
     *
     * @return array{0: string, 1: string}
     */
    public static function getEpisodeMuxPlayback(string $episodeHtml): array
    {
        $data = self::getData($episodeHtml);

        $lesson = $data['props']['lesson'] ?? [];

        if (empty($lesson['muxPlaybackId']) || empty($lesson['muxTokens']['playback'])) {
            throw new Exception('No Mux playback data found on the episode page.');
        }

        return [$lesson['muxPlaybackId'], $lesson['muxTokens']['playback']];
    }

    /**
     * Returns the Cloudflare HLS playback descriptor for the current
     * episode (the `src` master url plus a `captions` list), or null for
     * lessons still hosted on Mux.
     */
    public static function getEpisodeCloudflarePlayback(string $episodeHtml): ?array
    {
        $playback = self::getData($episodeHtml)['props']['lesson']['cloudflarePlayback'] ?? null;

        return empty($playback['src']) ? null : $playback;
    }

    /**
     * Returns the human-readable series title from an episode page
     * (e.g. 'Blaze Deep-Dive'), or null when unavailable.
     */
    public static function getSeriesTitle(string $episodeHtml): ?string
    {
        $data = self::getData($episodeHtml);

        return $data['props']['series']['title']
            ?? $data['props']['lesson']['series']['title']
            ?? null;
    }

    /**
     * Build chapter markers from the lesson transcript topic headers.
     * Returns an empty array for episodes without topic headers.
     *
     * @return array<int, array{title: string, start: int, end: int}> start/end in seconds
     */
    public static function getEpisodeChapters(string $episodeHtml): array
    {
        $data = self::getData($episodeHtml);

        $segments = $data['props']['lesson']['transcriptSegments'] ?? [];

        $chapters = [];

        foreach ($segments as $segment) {
            $title = trim((string) ($segment['topicHeader'] ?? ''));

            $startsNewChapter = $title !== ''
                && ($chapters === [] || $chapters[count($chapters) - 1]['title'] !== $title);

            if ($startsNewChapter) {
                if ($chapters !== []) {
                    $chapters[count($chapters) - 1]['end'] = (int) $segment['startTime'];
                }

                $chapters[] = [
                    'title' => $title,
                    'start' => (int) $segment['startTime'],
                    'end' => (int) $segment['endTime'],
                ];

                continue;
            }

            // header-less (or same-topic) segments extend the current chapter
            if ($chapters !== []) {
                $chapters[count($chapters) - 1]['end'] = (int) $segment['endTime'];
            }
        }

        return $chapters;
    }

    /**
     * Returns the current episode's original publish date from its page
     * (e.g. 'March 4, 2015'), or null for scheduled episodes.
     */
    public static function getEpisodePublishDate(string $episodeHtml): ?string
    {
        $data = self::getData($episodeHtml);

        return $data['props']['lesson']['dateSegments']['published'] ?? null;
    }

    /**
     * Returns each episode's original publish date keyed by episode
     * number, read from the series episode list of an episode page
     * (e.g. [1 => 'March 4, 2015', ...]). Episodes without a date
     * (scheduled ones) are omitted.
     *
     * @return array<int, string>
     */
    public static function getEpisodePublishDates(string $episodeHtml): array
    {
        $data = self::getData($episodeHtml);

        $dates = [];

        foreach ($data['props']['series']['chapters'] ?? [] as $chapter) {
            foreach ($chapter['episodes'] as $episode) {
                $published = $episode['dateSegments']['published'] ?? null;

                if (! empty($published)) {
                    $dates[(int) $episode['position']] = $published;
                }
            }
        }

        return $dates;
    }

    public static function getUserData(string $html): array
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
     * Returns decoded version of the Inertia page data in HTML page
     */
    public static function getData(string $html): array
    {
        $parser = new Crawler($html);

        // Inertia page data lives in a JSON script tag
        // (previously in the #app element's data-page attribute)
        $script = $parser->filter('script[data-page]');

        if ($script->count() > 0) {
            $json = $script->first()->text(null, false);
        } else {
            $app = $parser->filter('#app');
            $json = $app->count() > 0 ? $app->attr('data-page') : null;
        }

        if ($json === null || $json === '') {
            throw new Exception('Unable to find Inertia page data within the HTML.');
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Unable to decode Inertia page data: '.json_last_error_msg());
        }

        return $data;
    }

    public static function extractJsonAfter(string $html, string $needle): array
    {
        $needlePos = strpos($html, $needle);

        if ($needlePos === false) {
            throw new Exception("$needle not found within $html");
        }

        $openBracePos = strpos($html, '{', $needlePos);

        if ($openBracePos === false) {
            throw new Exception("No open curly brace found after $needle");
        }

        $braceCount = 1;
        $currentPos = $openBracePos + 1;
        $contentLength = strlen($html);

        while ($braceCount > 0 && $currentPos < $contentLength) {
            $nextOpenedBrace = strpos($html, '{', $currentPos);
            $nextClosedBrace = strpos($html, '}', $currentPos);

            if ($nextOpenedBrace === false && $nextClosedBrace === false) {
                break;
            }

            if ($nextOpenedBrace !== false && $nextOpenedBrace < $nextClosedBrace) {
                $braceCount++;
                $currentPos = $nextOpenedBrace + 1;
            } else {
                $braceCount--;
                $currentPos = $nextClosedBrace + 1;
            }
        }

        if ($braceCount !== 0) {
            throw new Exception('No valid json found');
        }

        $json = substr($html, $openBracePos, $currentPos - $openBracePos);

        if ($json === '' || $json === '0') {
            throw new Exception("Failed to extract json after $needle");
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(json_last_error_msg());
        }

        return $data;
    }
}
