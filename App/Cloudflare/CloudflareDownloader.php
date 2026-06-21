<?php

namespace App\Cloudflare;

use App\Mux\ChapterMetadata;
use App\Utils\Utils;
use GuzzleHttp\Client;
use Throwable;

/**
 * Downloads lessons hosted on Laracasts' Cloudflare CDN. The variant
 * already carries muxed audio, so it is a single-input ffmpeg remux;
 * the CDN is cookie-gated, so the login Cookie header is forwarded to
 * both the master fetch and ffmpeg.
 */
class CloudflareDownloader
{
    private readonly CloudflareRepository $repository;

    public function __construct()
    {
        $this->repository = new CloudflareRepository(new Client);
    }

    public function download(array $playback, string $cookieHeader, string $filepath, array $chapters = []): bool
    {
        $video = $this->repository->getMaster($playback['src'], $cookieHeader)->getVideoByQuality();

        Utils::writeln(sprintf('Downloading %dp video with ffmpeg...', $video['height']));

        $result = $this->remux(
            $video['url'],
            $cookieHeader,
            $filepath,
            ChapterMetadata::shouldEmbed() ? $chapters : []
        );

        if ($result && $chapters !== []) {
            $this->handleChapters($filepath, $chapters);
        }

        if ($result && $this->shouldDownloadSubtitles()) {
            $this->downloadCaptions($playback['captions'] ?? [], $cookieHeader, $filepath);
        }

        return $result;
    }

    /**
     * Fetch and save only the subtitle tracks of an episode (no video)
     */
    public function downloadSubtitlesOnly(array $playback, string $cookieHeader, string $filepath): bool
    {
        $captions = $playback['captions'] ?? [];

        if ($captions === []) {
            Utils::writeln('No subtitles for this episode.');

            return true;
        }

        $this->downloadCaptions($captions, $cookieHeader, $filepath);

        return true;
    }

    /**
     * Remux the muxed HLS variant straight into the target mp4 with ffmpeg
     * (stream copy), forwarding the login cookie to every segment request
     * and optionally attaching chapter markers from an ffmetadata side input.
     */
    private function remux(string $videoUrl, string $cookieHeader, string $outputPath, array $chapters): bool
    {
        // A single header passed without a trailing CRLF: ffmpeg accepts it
        // and, crucially, it survives escapeshellarg + cmd.exe on Windows
        // (an embedded \r\n would split the command and break the download).
        $inputs = sprintf(
            '-headers %s -i %s',
            escapeshellarg("Cookie: $cookieHeader"),
            escapeshellarg($videoUrl)
        );
        $maps = '';

        $metadataFile = null;

        if ($chapters !== []) {
            $metadataFile = ChapterMetadata::writeTempFile($chapters);

            $inputs .= sprintf(' -f ffmetadata -i %s', escapeshellarg($metadataFile));
            $maps = '-map_chapters 1';
        }

        $command = sprintf('ffmpeg -y -hide_banner -loglevel error %s %s -c copy %s 2>&1', $inputs, $maps, escapeshellarg($outputPath));

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
     * Report embedded chapters and/or save the ffmetadata sidecar, mirroring
     * the Mux downloader: an already-embedded sidecar goes into #merged/.
     */
    private function handleChapters(string $filepath, array $chapters): void
    {
        if (ChapterMetadata::shouldEmbed()) {
            Utils::writeln(sprintf('Embedded %d chapters', count($chapters)));
        }

        if (! ChapterMetadata::shouldSaveFile()) {
            return;
        }

        if (ChapterMetadata::shouldEmbed()) {
            ChapterMetadata::saveToMerged($filepath, $chapters);

            Utils::writeln('Saved chapters file to #merged');
        } else {
            ChapterMetadata::saveNextTo($filepath, $chapters);

            Utils::writeln('Saved chapters file');
        }
    }

    /**
     * Save every offered caption track as <base>.<lang>.vtt with a direct
     * authenticated GET (the captions are plain .vtt files, not HLS tracks).
     */
    private function downloadCaptions(array $captions, string $cookieHeader, string $filepath): void
    {
        $basePath = preg_replace('/\.[^.]+$/', '', $filepath);
        $client = new Client;

        foreach ($captions as $caption) {
            if (empty($caption['src'])) {
                continue;
            }

            $lang = $caption['language'] ?? 'en';

            try {
                $client->get($caption['src'], [
                    'headers' => ['Cookie' => $cookieHeader],
                    'sink' => "$basePath.$lang.vtt",
                    'verify' => false,
                ]);

                Utils::writeln("Downloaded subtitles ($lang)");
            } catch (Throwable) {
                Utils::writeln("Failed to download subtitles ($lang)");
            }
        }
    }

    private function shouldDownloadSubtitles(): bool
    {
        return filter_var($_ENV['DOWNLOAD_SUBTITLES'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }
}
