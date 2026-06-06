<?php

namespace App\Mux;

use App\Utils\Utils;

/**
 * Hands the signed Mux HLS master url to an external downloader
 * (yt-dlp recommended; the tool must accept `-o <file> <url>`).
 */
class ExternalDownloader
{
    public function download(string $playbackId, string $token, string $filepath, array $chapters = []): bool
    {
        $tool = $_ENV['EXTERNAL_TOOL'] ?? 'yt-dlp';

        $command = $this->buildCommand($tool, MuxRepository::masterURL($playbackId, $token), $filepath);

        Utils::writeln("Downloading with $tool...");

        $code = 0;

        passthru($command, $code);

        if ($code === 0) {
            if ($chapters !== [] && ChapterMetadata::shouldEmbed()) {
                $this->embedChapters($filepath, $chapters);
            }

            if ($chapters !== [] && ChapterMetadata::shouldSaveFile()) {
                ChapterMetadata::saveNextTo($filepath, $chapters);

                Utils::writeln('Saved chapters file');
            }

            return true;
        }

        Utils::write("$tool exited with code $code");

        $this->cleanupPartials($filepath);

        if ($this->shouldFallback()) {
            Utils::writeln('Falling back to the built-in mux downloader...');

            return (new MuxDownloader)->download($playbackId, $token, $filepath, $chapters);
        }

        return false;
    }

    /**
     * Remux in place with ffmpeg to attach chapter markers
     * (yt-dlp cannot inject custom chapters itself).
     */
    private function embedChapters(string $filepath, array $chapters): void
    {
        $metadataFile = ChapterMetadata::writeTempFile($chapters);
        $tempOutput = $filepath.'.chapters.mp4';

        $command = sprintf(
            'ffmpeg -y -hide_banner -loglevel error -i %s -f ffmetadata -i %s -map_chapters 1 -c copy %s 2>&1',
            escapeshellarg($filepath),
            escapeshellarg($metadataFile),
            escapeshellarg($tempOutput)
        );

        $output = [];
        $code = 0;

        exec($command, $output, $code);

        @unlink($metadataFile);

        if ($code === 0 && @unlink($filepath)) {
            rename($tempOutput, $filepath);

            Utils::writeln(sprintf('Embedded %d chapters', count($chapters)));

            return;
        }

        @unlink($tempOutput);

        Utils::writeln('Failed to embed chapters (is ffmpeg on PATH?)');
    }

    /**
     * EXTERNAL_TOOL_ARGS is appended verbatim; yt-dlp additionally gets
     * quality/subtitle flags derived from VIDEO_QUALITY and DOWNLOAD_SUBTITLES.
     */
    private function buildCommand(string $tool, string $url, string $filepath): string
    {
        $args = [trim($_ENV['EXTERNAL_TOOL_ARGS'] ?? '')];

        if (str_contains(strtolower(basename($tool)), 'yt-dlp')) {
            $height = (int) rtrim($_ENV['VIDEO_QUALITY'] ?? '', 'p');

            if ($height > 0) {
                $args[] = '-S '.escapeshellarg("res:$height");
            }

            $args[] = $this->formatSelector();
            $args[] = '--merge-output-format mp4';

            if (filter_var($_ENV['DOWNLOAD_SUBTITLES'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                $args[] = '--write-subs --sub-langs all';
            }
        }

        return sprintf(
            '%s %s -o %s %s 2>&1',
            str_contains($tool, ' ') ? escapeshellarg($tool) : $tool,
            implode(' ', array_filter($args)),
            escapeshellarg($filepath),
            escapeshellarg($url)
        );
    }

    /**
     * yt-dlp format selection. Dubbed series carry several audio renditions
     * (es, pt, de, ja) and without an explicit selector yt-dlp picks the
     * alphabetically last dub (e.g. Spanish) as "best" audio; the "Default"
     * rendition is the original audio track. AUDIO_LANGUAGE opts into a dub.
     */
    private function formatSelector(): string
    {
        $selectors = [];

        $language = $_ENV['AUDIO_LANGUAGE'] ?? '';

        if ($language !== '') {
            $selectors[] = "bv*+ba[language=$language]";
        }

        $selectors[] = 'bv*+ba[format_id*=Default]';
        $selectors[] = 'bv*+ba';
        $selectors[] = 'b';

        return '-f '.escapeshellarg(implode('/', $selectors));
    }

    private function shouldFallback(): bool
    {
        return filter_var($_ENV['EXTERNAL_TOOL_FALLBACK'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Remove partial downloads so half-written files are not picked up later
     */
    private function cleanupPartials(string $filepath): void
    {
        foreach ([$filepath.'.part', $filepath.'.ytdl'] as $partial) {
            if (file_exists($partial)) {
                @unlink($partial);
            }
        }
    }
}
