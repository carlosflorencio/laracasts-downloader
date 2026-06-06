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

                // muxPlaybackId (vimeoId on legacy pages) is null for upcoming episodes
                if (empty($episode['muxPlaybackId']) && empty($episode['vimeoId'])) {
                    continue;
                }

                $episodes[] = [
                    'title' => $episode['title'],
                    'vimeo_id' => $episode['vimeoId'] ?? null,
                    'number' => $episode['position'],
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
