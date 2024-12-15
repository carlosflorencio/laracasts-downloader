<?php

namespace App\Vimeo\DTO;

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\UriInterface;

class MasterDTO
{
    private ?array $videos = null;

    private ?array $audios = null;

    private ?string $masterURL = null;

    private ?string $baseURL = null;

    private ?string $clipId = null;

    public function getVideos(): array
    {
        return array_map(function (array $video) {
            $video['extension'] = '.m4v';

            return $video;
        }, $this->videos);
    }

    public function setVideos(array $videos): static
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

    public function setAudios(array $audios): static
    {
        $this->audios = $audios;

        return $this;
    }

    /**
     * Get video by id or the one with the highest quality
     */
    public function getVideoById(?string $id): array
    {
        $videos = $this->getVideos();

        if (! is_null($id)) {
            $ids = array_column($videos, 'id');
            $key = array_search($id, $ids);

            if ($key !== false) {
                return $videos[$key];
            }

            // Previously, the Vimeo ID matched the first segment of the UUID.
            // So we keep it for backward compatibility
            $key = array_search(explode('-', $id)[0], $ids);

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
     * @return $this
     */
    public function setMasterURL(string $masterURL): static
    {
        $this->masterURL = $masterURL;

        return $this;
    }

    public function getBaseURL(): ?string
    {
        return $this->baseURL;
    }

    public function setBaseURL(string $baseURL): static
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

    public function getClipId(): ?string
    {
        return $this->clipId;
    }

    public function setClipId(string $clipId): static
    {
        $this->clipId = $clipId;

        return $this;
    }
}
