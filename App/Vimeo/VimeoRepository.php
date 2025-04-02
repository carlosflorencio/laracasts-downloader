<?php

namespace App\Vimeo;

use App\Vimeo\DTO\MasterDTO;
use App\Vimeo\DTO\VideoDTO;
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
        preg_match('/"(?:google_skyfire|akfire_interconnect_quic)":({.+?avc_url.+?})/', $content, $cdns);
        preg_match('/"text_tracks":(\[{.+?}\])/', $content, $subtitles);

        $vimeo = new VideoDTO;

        $vimeo->setMasterURL(json_decode($cdns[1], true)['url'])
            ->setStreams(json_decode($streams[1], true));

        if (isset($subtitles[1])) {
            $vimeo->setSubtitles(json_decode($subtitles[1], true));
        }

        return $vimeo;
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
}
