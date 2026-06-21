<?php

namespace App\Hls;

/**
 * Parses an HLS master playlist (m3u8) into video variants, audio
 * renditions and subtitle tracks. Shared by the Mux and Cloudflare
 * download paths.
 */
class MasterPlaylistParser
{
    /**
     * @return array{videos: array, audios: array, subtitles: array}
     */
    public static function parse(string $content, ?string $baseUrl = null): array
    {
        $videos = [];
        $audios = [];
        $subtitles = [];

        $lines = preg_split('/\r?\n/', $content);
        $total = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $line = trim($lines[$i]);

            if (str_starts_with($line, '#EXT-X-MEDIA:')) {
                $attributes = self::parseAttributes($line);

                if (($attributes['TYPE'] ?? null) === 'AUDIO') {
                    $audios[] = [
                        'group_id' => $attributes['GROUP-ID'] ?? null,
                        'language' => $attributes['LANGUAGE'] ?? null,
                        'default' => ($attributes['DEFAULT'] ?? 'NO') === 'YES',
                        'url' => self::resolve($baseUrl, $attributes['URI'] ?? null),
                    ];
                } elseif (($attributes['TYPE'] ?? null) === 'SUBTITLES') {
                    $subtitles[] = [
                        'language' => $attributes['LANGUAGE'] ?? 'en',
                        'name' => $attributes['NAME'] ?? null,
                        'url' => self::resolve($baseUrl, $attributes['URI'] ?? null),
                    ];
                }
            } elseif (str_starts_with($line, '#EXT-X-STREAM-INF:')) {
                $attributes = self::parseAttributes($line);

                preg_match('/\d+x(\d+)/', $attributes['RESOLUTION'] ?? '', $resolution);

                // the variant URI is on the line following its #EXT-X-STREAM-INF tag
                $videos[] = [
                    'height' => isset($resolution[1]) ? (int) $resolution[1] : 0,
                    'bandwidth' => (int) ($attributes['BANDWIDTH'] ?? 0),
                    'audio_group' => $attributes['AUDIO'] ?? null,
                    'url' => self::resolve($baseUrl, trim($lines[$i + 1] ?? '')),
                ];
            }
        }

        return ['videos' => $videos, 'audios' => $audios, 'subtitles' => $subtitles];
    }

    /**
     * Parse an M3U8 attribute list, honoring quoted values
     * (e.g. CODECS="mp4a.40.2,avc1.64002a" contains a comma).
     */
    private static function parseAttributes(string $line): array
    {
        $attributes = [];

        preg_match_all('/([A-Z0-9-]+)=("[^"]*"|[^,]*)/', $line, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = trim($match[2], '"');
        }

        return $attributes;
    }

    /**
     * Resolve a possibly-relative playlist URI against the master URL.
     * Mux serves absolute (signed) URIs which pass through unchanged;
     * Cloudflare serves path-relative ones (e.g. "720p/index.m3u8").
     */
    private static function resolve(?string $baseUrl, ?string $uri): ?string
    {
        if ($uri === null || $uri === '' || $baseUrl === null) {
            return $uri;
        }

        if (str_contains($uri, '://')) {
            return $uri;
        }

        $parts = parse_url($baseUrl);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($uri, '/')) {
            return $origin.$uri;
        }

        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

        return $origin.$dir.$uri;
    }
}
