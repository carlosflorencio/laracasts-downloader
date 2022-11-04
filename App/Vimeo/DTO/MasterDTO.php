<?php

namespace App\Vimeo\DTO;

use GuzzleHttp\Psr7;
use Psr\Http\Message\UriInterface;

class MasterDTO
{
    /** @var array */
    private $videos;

    /** @var array */
    private $audios;

    /** @var string */
    private $masterURL;

    /** @var string */
    private $baseURL;

    /** @var string */
    private $clipId;

    /**
     * @return array
     */
    public function getVideos()
    {
        return array_map(function($video) {
            $video['extension'] = '.m4v';

            return $video;
        }, $this->videos);
    }

    /**
     * @param  array  $videos
     *
     * @return self
     */
    public function setVideos($videos)
    {
        $this->videos = $videos;

        return $this;
    }

    /**
     * @return array
     */
    public function getAudios()
    {
        return array_map(function($audio) {
            $audio['extension'] = '.m4a';

            return $audio;
        }, $this->audios);
    }

    /**
     * @param  array  $audios
     *
     * @return self
     */
    public function setAudios($audios)
    {
        $this->audios = $audios;

        return $this;
    }

    /**
     * Get video by id or the one with the highest quality
     *
     * @param  null|string  $id
     *
     * @return array
     */
    public function getVideoById($id)
    {
        $videos = $this->getVideos();

        if (! is_null($id)) {
            $ids = array_column($videos, 'id');
            $key = array_search($id, $ids);

            if ($key !== false) {
                return $videos[$key];
            }
        }

        usort($videos, function($a, $b) {
            return $a['height'] <=> $b['height'];
        });

        return end($videos);
    }

    public function getAudio()
    {
        $audios = $this->getAudios();

        usort($audios, function($a, $b) {
            return $a['bitrate'] <=> $b['bitrate'];
        });

        return end($audios);
    }

    /**
     * @return UriInterface
     */
    public function getMasterURL()
    {
        return Psr7\Utils::uriFor($this->masterURL);
    }

    /**
     * @param  string  $masterURL
     *
     * @return $this
     */
    public function setMasterURL($masterURL)
    {
        $this->masterURL = $masterURL;

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseURL()
    {
        return $this->baseURL;
    }

    /**
     * @param  string  $baseURL
     *
     * @return self
     */
    public function setBaseURL($baseURL)
    {
        $this->baseURL = $baseURL;

        return $this;
    }

    /**
     * Make final URL from combination of absolute and relate ones
     * @return string
     */
    public function resolveURL($url)
    {
        return (string)Psr7\UriResolver::resolve(
            $this->getMasterURL(),
            Psr7\Utils::uriFor($this->getBaseURL().$url)
        );
    }

    /**
     * @return string
     */
    public function getClipId()
    {
        return $this->clipId;
    }

    /**
     * @param  string  $clipId
     *
     * @return self
     */
    public function setClipId($clipId)
    {
        $this->clipId = $clipId;

        return $this;
    }

}
