<?php

namespace App\Vimeo\DTO;

class VideoDTO
{
    private ?string $masterURL = null;

    private ?array $streams = null;

    private ?array $subtitles = null;

    public function getMasterURL(): ?string
    {
        return $this->masterURL;
    }

    public function setMasterURL(string $masterURL): static
    {
        $this->masterURL = $masterURL;

        return $this;
    }

    public function getStreams(): ?array
    {
        return $this->streams;
    }

    public function setStreams(array $streams): static
    {
        $this->streams = $streams;

        return $this;
    }

    public function getSubtitles(): ?array
    {
        return $this->subtitles;
    }

    public function setSubtitles(array $subtitles): static
    {
        $this->subtitles = $subtitles;

        return $this;
    }

    public function getVideoIdByQuality(): ?string
    {
        foreach ($this->getStreams() as $stream) {
            if ($stream['quality'] === $_ENV['VIDEO_QUALITY']) {
                return $stream['id'];
            }
        }

        return null;
    }
}
