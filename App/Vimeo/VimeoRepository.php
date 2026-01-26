<?php

namespace App\Vimeo;

use App\Html\Parser;
use App\Vimeo\DTO\MasterDTO;
use App\Vimeo\DTO\VideoDTO;
use Exception;
use GuzzleHttp\Client;

class VimeoRepository
{
    public function __construct(private readonly Client $client) {}

    public function get($vimeoId): VideoDTO
    {
        $content = $this->client->get("https://player.vimeo.com/video/$vimeoId", [
            'headers' => [
                'Referer' => 'https://laracasts.com/',
            ],
        ])
            ->getBody()
            ->getContents();

        preg_match('/"streams":(\[{.+?}\])/', $content, $streams);

        preg_match('/"(?:google_skyfire|akfire_interconnect_quic)":({.+?})/', $content, $cdns);

        if (empty($cdns[1])) {
            throw new Exception("could not find cdn for vimeo $vimeoId within: $content");
        }

        $video = (new VideoDTO)->setStreams(json_decode($streams[1], true));

        $decoded = json_decode($cdns[1], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $video->setMasterURL($decoded['url']);
        } else {
            $config = Parser::extractJsonAfter($content, 'window.playerConfig');

            $url = $config['request']['files']['dash']['cdns']['akfire_interconnect_quic']['url'] ??
                $config['request']['files']['dash']['cdns']['google_skyfire']['url'] ?? null;

            if ($url === null) {
                throw new Exception('could not find proper CDN for master URL.');
            }

            $video->setMasterURL($url);
        }

        // Extract text tracks (subtitles/captions)
        $textTracks = $this->extractTextTracks($content);
        $video->setTextTracks($textTracks);

        return $video;
    }

    public function getMaster(VideoDTO $video): MasterDTO
    {
        $content = $this->client->get($video->getMasterURL())
            ->getBody()
            ->getContents();

        $data = json_decode($content, true);

        $master = new MasterDTO;

        return $master
            ->setMasterURL($video->getMasterURL())
            ->setBaseURL($data['base_url'])
            ->setClipId($data['clip_id'])
            ->setAudios($data['audio'])
            ->setVideos($data['video']);
    }

    /**
     * Extract text tracks (subtitles/captions) from Vimeo player page
     */
    private function extractTextTracks(string $content): array
    {
        // Try to match text_tracks from the player config
        if (preg_match('/"text_tracks":(\[.*?\])(?=,"|,\s*"|\})/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback: try extracting from window.playerConfig
        $config = Parser::extractJsonAfter($content, 'window.playerConfig');
        if (isset($config['request']['text_tracks']) && is_array($config['request']['text_tracks'])) {
            return $config['request']['text_tracks'];
        }

        return [];
    }
}
