<?php

namespace App\Vimeo\DTO;

class VideoDTO
{
    /**
     * @var string
     */
    private $masterURL;

    /**
     * @var array
     */
    private $streams;

    /**
     * @return string
     */
    public function getMasterURL()
    {
        return $this->masterURL;
    }

    /**
     * @param  string  $masterURL
     * @return self
     */
    public function setMasterURL($masterURL)
    {
        $this->masterURL = $masterURL;

        return $this;
    }

    /**
     * @return array
     */
    public function getStreams()
    {
        return $this->streams;
    }

    /**
     * @return self
     */
    public function setStreams(array $streams)
    {
        $this->streams = $streams;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getVideoIdByQuality()
    {
        $id = null;

        foreach ($this->getStreams() as $stream) {
            if ($stream['quality'] === getenv('VIDEO_QUALITY')) {
                $id = explode('-', (string) $stream['id'])[0];
            }
        }

        return $id;
    }
}
