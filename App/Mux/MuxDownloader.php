<?php

namespace App\Mux;

use App\Utils\Utils;
use GuzzleHttp\Client;

class MuxDownloader
{
    private readonly MuxRepository $repository;

    public function __construct()
    {
        $this->repository = new MuxRepository(new Client);
    }

    public function download(string $playbackId, string $token, string $filepath, array $chapters = []): bool
    {
        $master = $this->repository->getMaster($playbackId, $token);

        $video = $master->getVideoByQuality();
        $audio = $master->getAudioByGroupId($video['audio_group']);

        Utils::writeln(sprintf('Downloading %dp video and audio with ffmpeg...', $video['height']));

        $result = $this->downloadAndMerge(
            $video['url'],
            $audio['url'] ?? null,
            $filepath,
            ChapterMetadata::shouldEmbed() ? $chapters : []
        );

        if ($result && $chapters !== []) {
            if (ChapterMetadata::shouldEmbed()) {
                Utils::writeln(sprintf('Embedded %d chapters', count($chapters)));
            }

            if (ChapterMetadata::shouldSaveFile()) {
                ChapterMetadata::saveNextTo($filepath, $chapters);

                Utils::writeln('Saved chapters file');
            }
        }

        if ($result && $this->shouldDownloadSubtitles()) {
            $this->downloadSubtitles($master->getSubtitles(), $filepath);
        }

        return $result;
    }

    /**
     * Fetch and save only the subtitle tracks of an episode (no video)
     */
    public function downloadSubtitlesOnly(string $playbackId, string $token, string $filepath): bool
    {
        $subtitles = $this->repository->getMaster($playbackId, $token)->getSubtitles();

        if ($subtitles === []) {
            Utils::writeln('No subtitles for this episode.');

            return true;
        }

        $this->downloadSubtitles($subtitles, $filepath);

        return true;
    }

    /**
     * Check if subtitles should be downloaded
     */
    private function shouldDownloadSubtitles(): bool
    {
        $setting = $_ENV['DOWNLOAD_SUBTITLES'] ?? 'false';

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Remux the HLS video and audio renditions straight into the target
     * mp4 with ffmpeg (stream copy, no re-encoding), optionally attaching
     * chapter markers from an ffmetadata side input.
     */
    private function downloadAndMerge(string $videoURL, ?string $audioURL, string $outputPath, array $chapters = []): bool
    {
        // all -i inputs must precede output options like -map / -map_chapters
        if ($audioURL === null) {
            $inputs = sprintf('-i %s', escapeshellarg($videoURL));
            $maps = '';
        } else {
            $inputs = sprintf('-i %s -i %s', escapeshellarg($videoURL), escapeshellarg($audioURL));
            $maps = '-map 0:v:0 -map 1:a:0';
        }

        $metadataFile = null;

        if ($chapters !== []) {
            $metadataFile = ChapterMetadata::writeTempFile($chapters);

            $inputs .= sprintf(' -f ffmetadata -i %s', escapeshellarg($metadataFile));
            $maps .= sprintf(' -map_chapters %d', $audioURL === null ? 1 : 2);
        }

        $command = sprintf('ffmpeg -y -hide_banner -loglevel error %s %s -c copy %s 2>&1', $inputs, trim($maps), escapeshellarg($outputPath));

        $output = [];
        $code = 0;

        exec($command, $output, $code);

        if ($metadataFile !== null) {
            @unlink($metadataFile);
        }

        if ($code === 0) {
            return true;
        }

        Utils::write('ffmpeg failed: '.implode(PHP_EOL, array_slice($output, -5)));

        return false;
    }

    /**
     * Download subtitle renditions as .vtt files next to the episode
     */
    private function downloadSubtitles(array $subtitles, string $filepath): void
    {
        $basePath = preg_replace('/\.[^.]+$/', '', $filepath);

        foreach ($subtitles as $subtitle) {
            if (empty($subtitle['url'])) {
                continue;
            }

            $lang = $subtitle['language'];

            // allowed_extensions ALL: the HLS demuxer rejects Mux's
            // signed .vtt segment URLs (query string) by default
            $command = sprintf(
                'ffmpeg -y -hide_banner -loglevel error -allowed_extensions ALL -i %s %s 2>&1',
                escapeshellarg((string) $subtitle['url']),
                escapeshellarg("$basePath.$lang.vtt")
            );

            $output = [];
            $code = 0;

            exec($command, $output, $code);

            if ($code === 0) {
                Utils::writeln("Downloaded subtitles ($lang)");
            } else {
                Utils::writeln("Failed to download subtitles ($lang)");
            }
        }
    }
}
