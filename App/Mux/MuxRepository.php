<?php

namespace App\Mux;

use App\Hls\MasterPlaylistDTO;
use App\Hls\MasterPlaylistParser;
use GuzzleHttp\Client;

class MuxRepository
{
    private const string STREAM_URL = 'https://stream.mux.com';

    public function __construct(private readonly Client $client) {}

    /**
     * Signed HLS master playlist URL for a playback id
     */
    public static function masterURL(string $playbackId, string $token): string
    {
        return self::STREAM_URL."/$playbackId.m3u8?token=$token";
    }

    public function getMaster(string $playbackId, string $token): MasterPlaylistDTO
    {
        $url = self::masterURL($playbackId, $token);

        $content = $this->client->get($url)
            ->getBody()
            ->getContents();

        $parsed = MasterPlaylistParser::parse($content, $url);

        return (new MasterPlaylistDTO)
            ->setVideos($parsed['videos'])
            ->setAudios($parsed['audios'])
            ->setSubtitles($parsed['subtitles']);
    }
}
