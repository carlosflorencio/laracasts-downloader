<?php

namespace App\Vimeo;

use App\Vimeo\DTO\MasterDTO;
use App\Vimeo\DTO\VideoDTO;
use GuzzleHttp\Client;

class VimeoRepository
{
    /** @var Client */
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @return VideoDTO
     */
    public function get($vimeoId)
    {
        $content = $this->client->get("https://player.vimeo.com/video/$vimeoId", [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                'Referer' => 'https://laracasts.com',
                'Origin' => 'https://laracasts.com',
            ],
            'verify' => false,
        ])
            ->getBody()
            ->getContents();

        preg_match('/"streams":(\[{.+?}\])/', $content, $streams);

        preg_match('/"(?:google_skyfire|akfire_interconnect_quic)":({.+?})/', $content, $cdns);

        $vimeo = new VideoDTO();
        return $vimeo->setMasterURL(json_decode($cdns[1], true)['url'])
            ->setStreams(json_decode($streams[1], true));
    }

    /**
     * @param  VideoDTO  $video
     * @return MasterDTO
     */
    public function getMaster($video)
    {
        $content = $this->client->get($video->getMasterURL())
            ->getBody()
            ->getContents();

        $data = json_decode($content, true);

        $master = new MasterDTO();
        return $master
            ->setMasterURL($video->getMasterURL())
            ->setBaseURL($data['base_url'])
            ->setClipId($data['clip_id'])
            ->setAudios($data['audio'])
            ->setVideos($data['video']);
    }
}
