<?php

namespace App\Cloudflare;

use App\Hls\MasterPlaylistDTO;
use App\Hls\MasterPlaylistParser;
use GuzzleHttp\Client;

class CloudflareRepository
{
    public function __construct(private readonly Client $client) {}

    /**
     * Fetch and parse the HLS master playlist from the Cloudflare CDN.
     * The CDN is cookie-gated, so the login Cookie header must be sent.
     */
    public function getMaster(string $masterUrl, string $cookieHeader): MasterPlaylistDTO
    {
        $content = $this->client->get($masterUrl, [
            'headers' => ['Cookie' => $cookieHeader],
            'verify' => false,
        ])->getBody()->getContents();

        $parsed = MasterPlaylistParser::parse($content, $masterUrl);

        // Cloudflare muxes audio into each variant, so audios/subtitles are
        // empty here; subtitles come from the lesson page's captions list.
        return (new MasterPlaylistDTO)
            ->setVideos($parsed['videos'])
            ->setAudios($parsed['audios'])
            ->setSubtitles($parsed['subtitles']);
    }
}
