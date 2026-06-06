<?php

namespace App\Mux;

use App\Mux\DTO\MasterPlaylistDTO;
use GuzzleHttp\Client;

class MuxRepository
{
    private const string STREAM_URL = 'https://stream.mux.com';

    public function __construct(private readonly Client $client) {}

    public function getMaster(string $playbackId, string $token): MasterPlaylistDTO
    {
        $content = $this->client->get(self::STREAM_URL."/$playbackId.m3u8?token=$token")
            ->getBody()
            ->getContents();

        return $this->parseMasterPlaylist($content);
    }

    private function parseMasterPlaylist(string $content): MasterPlaylistDTO
    {
        $videos = [];
        $audios = [];
        $subtitles = [];

        $lines = preg_split('/\r?\n/', $content);
        $total = count($lines);

        for ($i = 0; $i < $total; $i++) {
            $line = trim($lines[$i]);

            if (str_starts_with($line, '#EXT-X-MEDIA:')) {
                $attributes = $this->parseAttributes($line);

                if (($attributes['TYPE'] ?? null) === 'AUDIO') {
                    $audios[] = [
                        'group_id' => $attributes['GROUP-ID'] ?? null,
                        'url' => $attributes['URI'] ?? null,
                    ];
                } elseif (($attributes['TYPE'] ?? null) === 'SUBTITLES') {
                    $subtitles[] = [
                        'language' => $attributes['LANGUAGE'] ?? 'en',
                        'name' => $attributes['NAME'] ?? null,
                        'url' => $attributes['URI'] ?? null,
                    ];
                }
            } elseif (str_starts_with($line, '#EXT-X-STREAM-INF:')) {
                $attributes = $this->parseAttributes($line);

                preg_match('/\d+x(\d+)/', $attributes['RESOLUTION'] ?? '', $resolution);

                // the variant URI is on the line following its #EXT-X-STREAM-INF tag
                $videos[] = [
                    'height' => isset($resolution[1]) ? (int) $resolution[1] : 0,
                    'bandwidth' => (int) ($attributes['BANDWIDTH'] ?? 0),
                    'audio_group' => $attributes['AUDIO'] ?? null,
                    'url' => trim($lines[$i + 1] ?? ''),
                ];
            }
        }

        return (new MasterPlaylistDTO)
            ->setVideos($videos)
            ->setAudios($audios)
            ->setSubtitles($subtitles);
    }

    /**
     * Parse an M3U8 attribute list, honoring quoted values
     * (e.g. CODECS="mp4a.40.2,avc1.64002a" contains a comma).
     */
    private function parseAttributes(string $line): array
    {
        $attributes = [];

        preg_match_all('/([A-Z0-9-]+)=("[^"]*"|[^,]*)/', $line, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = trim($match[2], '"');
        }

        return $attributes;
    }
}
