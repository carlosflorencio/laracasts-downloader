<?php

namespace App\Mux\DTO;

use Exception;

class MasterPlaylistDTO
{
    private array $videos = [];

    private array $audios = [];

    private array $subtitles = [];

    public function setVideos(array $videos): static
    {
        $this->videos = $videos;

        return $this;
    }

    public function setAudios(array $audios): static
    {
        $this->audios = $audios;

        return $this;
    }

    public function setSubtitles(array $subtitles): static
    {
        $this->subtitles = $subtitles;

        return $this;
    }

    public function getSubtitles(): array
    {
        return $this->subtitles;
    }

    /**
     * Returns the variant matching VIDEO_QUALITY (e.g. "1080p"),
     * falling back to the highest available quality.
     */
    public function getVideoByQuality(): array
    {
        if ($this->videos === []) {
            throw new Exception('No video variants found in the Mux master playlist.');
        }

        $height = (int) rtrim($_ENV['VIDEO_QUALITY'] ?? '', 'p');

        $videos = array_filter($this->videos, fn (array $video): bool => $video['height'] === $height);

        if ($videos === []) {
            $videos = $this->videos;
        }

        usort($videos, fn (array $a, array $b): int => [$b['height'], $b['bandwidth']] <=> [$a['height'], $a['bandwidth']]);

        return $videos[0];
    }

    /**
     * Returns the audio rendition for the given group id
     * (audio is served as a separate rendition, not muxed into the variant).
     */
    public function getAudioByGroupId(?string $groupId): ?array
    {
        foreach ($this->audios as $audio) {
            if ($audio['group_id'] === $groupId) {
                return $audio;
            }
        }

        return $this->audios[0] ?? null;
    }
}
