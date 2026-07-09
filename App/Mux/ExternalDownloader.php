<?php

namespace App\Mux;

use App\Cloudflare\CloudflareDownloader;
use App\Utils\SubtitleLanguages;
use App\Utils\Subtitles;
use App\Utils\Utils;
use GuzzleHttp\Client;
use Throwable;

/**
 * Hands a signed HLS master url to an external downloader (yt-dlp
 * recommended; the tool must accept `-o <file> <url>`). Works for both
 * Mux (signed url) and Cloudflare (url + forwarded Cookie header) lessons.
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
            $this->persistChapters($filepath, $chapters);

            if (Subtitles::enabled()) {
                $this->handleMuxSubtitles($playbackId, $token, $filepath);
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
     * Hand a ready (Cloudflare) HLS master url to the external tool with the
     * login cookie forwarded as a request header. Falls back to the built-in
     * Cloudflare downloader on failure when EXTERNAL_TOOL_FALLBACK is set.
     */
    public function downloadFromUrl(array $playback, string $cookieHeader, string $filepath, array $chapters = []): bool
    {
        $tool = $_ENV['EXTERNAL_TOOL'] ?? 'yt-dlp';

        $command = $this->buildCommand($tool, $playback['src'], $filepath, $cookieHeader);

        Utils::writeln("Downloading with $tool...");

        $code = 0;

        passthru($command, $code);

        if ($code === 0) {
            $this->persistChapters($filepath, $chapters);

            // Cloudflare captions live in the lesson props, not the HLS
            // manifest, so the external tool cannot see them: fetch + deliver
            // them ourselves (honoring embed/sidecar/both).
            if (Subtitles::enabled()) {
                $captions = CloudflareDownloader::selectCaptions($playback['captions'] ?? []);
                Subtitles::deliver($filepath, Subtitles::materializeDirect($captions, $cookieHeader));
            }

            return true;
        }

        Utils::write("$tool exited with code $code");

        $this->cleanupPartials($filepath);

        if ($this->shouldFallback()) {
            Utils::writeln('Falling back to the built-in cloudflare downloader...');

            return (new CloudflareDownloader)->download($playback, $cookieHeader, $filepath, $chapters);
        }

        return false;
    }

    /**
     * Embed chapter markers and/or save the ffmetadata sidecar into the
     * chapters/ subfolder after a successful external download.
     */
    private function persistChapters(string $filepath, array $chapters): void
    {
        if ($chapters === []) {
            return;
        }

        if (ChapterMetadata::shouldEmbed()) {
            $this->embedChapters($filepath, $chapters);
        }

        if (ChapterMetadata::shouldSaveFile()) {
            ChapterMetadata::saveToChapters($filepath, $chapters);

            Utils::writeln('Saved chapters file to chapters/');
        }
    }

    /**
     * Remux in place with ffmpeg to attach chapter markers
     * (yt-dlp cannot inject custom chapters itself).
     */
    private function embedChapters(string $filepath, array $chapters): bool
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

            return true;
        }

        @unlink($tempOutput);

        Utils::writeln('Failed to embed chapters (is ffmpeg on PATH?)');

        return false;
    }

    /**
     * Fetch the Mux subtitle renditions from the master playlist and embed /
     * save them per DOWNLOAD_SUBTITLES (yt-dlp is not asked for subs so the
     * embed/sidecar behavior is identical to the Cloudflare path).
     */
    private function handleMuxSubtitles(string $playbackId, string $token, string $filepath): void
    {
        try {
            $subtitles = (new MuxRepository(new Client))->getMaster($playbackId, $token)->getSubtitles();
        } catch (Throwable $e) {
            Utils::writeln('Failed to fetch subtitles: '.$e->getMessage());

            return;
        }

        $selected = SubtitleLanguages::filter($subtitles);

        if ($selected === []) {
            return;
        }

        Subtitles::deliver($filepath, Subtitles::materializeHls($selected));
    }

    /**
     * EXTERNAL_TOOL_ARGS is appended verbatim; yt-dlp additionally gets
     * quality flags derived from VIDEO_QUALITY (subtitles are fetched and
     * embedded/saved separately, uniformly across sources).
     */
    private function buildCommand(string $tool, string $url, string $filepath, string $cookieHeader = ''): string
    {
        $args = [trim($_ENV['EXTERNAL_TOOL_ARGS'] ?? '')];

        if (str_contains(strtolower(basename($tool)), 'yt-dlp')) {
            $height = (int) rtrim($_ENV['VIDEO_QUALITY'] ?? '', 'p');

            if ($height > 0) {
                $args[] = '-S '.escapeshellarg("res:$height");
            }

            $args[] = $this->formatSelector();
            $args[] = '--merge-output-format mp4';

            // Cloudflare lessons are cookie-gated; forward the login cookie
            if ($cookieHeader !== '') {
                $args[] = '--add-header '.escapeshellarg("Cookie:$cookieHeader");
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
