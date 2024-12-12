<?php

namespace App\Vimeo\DTO;

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
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

    public function getVideos(): array
    {
        return array_map(function (array $video) {
            $video['extension'] = '.m4v';

            return $video;
        }, $this->videos);
    }

    /**
     * @param  array  $videos
     */
    public function setVideos($videos): static
    {
        $this->videos = $videos;

        return $this;
    }

    public function getAudios(): array
    {
        return array_map(function (array $audio) {
            $audio['extension'] = '.m4a';

            return $audio;
        }, $this->audios);
    }

    /**
     * @param  array  $audios
     */
    public function setAudios($audios): static
    {
        $this->audios = $audios;

        return $this;
    }

    /**
     * Get video by id or the one with the highest quality
     *
     * @param  null|string  $id
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

        usort($videos, fn ($a, $b): int => $a['height'] <=> $b['height']);

        return end($videos);
    }

    public function getAudio(): mixed
    {
        $audios = $this->getAudios();

        usort($audios, fn ($a, $b): int => $a['bitrate'] <=> $b['bitrate']);

        return end($audios);
    }

    public function getMasterURL(): UriInterface
    {
        return Utils::uriFor($this->masterURL);
    }

    /**
     * @param  string  $masterURL
     * @return $this
     */
    public function setMasterURL($masterURL): static
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
     */
    public function setBaseURL($baseURL): static
    {
        $this->baseURL = $baseURL;

        return $this;
    }

    /**
     * Make final URL from combination of absolute and relate ones
     */
    public function resolveURL(string $url): string
    {
        return (string) UriResolver::resolve(
            $this->getMasterURL(),
            Utils::uriFor($this->getBaseURL().$url)
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
     */
    public function setClipId($clipId): static
    {
        $this->clipId = $clipId;

        return $this;
    }
}
