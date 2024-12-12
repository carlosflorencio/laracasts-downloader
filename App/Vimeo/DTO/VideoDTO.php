<?php

namespace App\Vimeo\DTO;

class VideoDTO
{
    /**
     * @var string
     */
    private $masterURL;

    private ?array $streams = null;

    /**
     * @return string
     */
    public function getMasterURL()
    {
        return $this->masterURL;
    }

    /**
     * @param  string  $masterURL
     */
    public function setMasterURL($masterURL): static
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

    public function getVideoIdByQuality(): ?string
    {
        $id = null;

        foreach ($this->getStreams() as $stream) {
            if ($stream['quality'] === $_ENV['VIDEO_QUALITY']) {
                $id = explode('-', (string) $stream['id'])[0];
            }
        }

        return $id;
    }
}
