<?php

namespace App\Mux;

use App\Utils\SubtitleLanguages;
use App\Utils\Subtitles;
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
                // already embedded above -> sidecar belongs in #merged/
                if (ChapterMetadata::shouldEmbed()) {
                    ChapterMetadata::saveToMerged($filepath, $chapters);

                    Utils::writeln('Saved chapters file to #merged');
                } else {
                    ChapterMetadata::saveNextTo($filepath, $chapters);

                    Utils::writeln('Saved chapters file');
                }
            }
        }

        if ($result && Subtitles::enabled()) {
            Subtitles::deliver($filepath, Subtitles::materializeHls(SubtitleLanguages::filter($master->getSubtitles())));
        }

        return $result;
    }

    /**
     * Fetch and save only the subtitle tracks of an episode (no video).
     * Always writes sidecar .vtt files into subs/ (the subtitles-only flag).
     */
    public function downloadSubtitlesOnly(string $playbackId, string $token, string $filepath): bool
    {
        $subtitles = $this->repository->getMaster($playbackId, $token)->getSubtitles();

        if ($subtitles === []) {
            Utils::writeln('No subtitles for this episode.');

            return true;
        }

        $tracks = Subtitles::materializeHls(SubtitleLanguages::filter($subtitles));

        if ($tracks !== []) {
            Subtitles::saveSidecars($filepath, $tracks);

            Utils::writeln('Saved subtitles to subs/');

            foreach ($tracks as $track) {
                @unlink($track['path']);
            }
        }

        return true;
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
}
