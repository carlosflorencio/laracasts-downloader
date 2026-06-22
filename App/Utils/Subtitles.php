<?php

namespace App\Utils;

use GuzzleHttp\Client;
use Throwable;

/**
 * Subtitle delivery, mirroring DOWNLOAD_CHAPTERS. The DOWNLOAD_SUBTITLES env:
 *
 *   - 'embed' (or a plain boolean true) muxes the .vtt tracks into the mp4
 *     as mov_text streams
 *   - 'sidecar' (alias 'file') saves them as .vtt in a 'subs' subfolder
 *   - 'both' does both
 *   - empty / false / off disables subtitles
 *
 * Each downloader builds a track list (language + url/+default), filters it
 * by SUBTITLE_LANGUAGE, materialises the chosen tracks to temp .vtt files,
 * then embeds and/or saves them. A track is an associative array carrying at
 * least a 'language', plus a source key ('url' for HLS, 'src' for a direct
 * .vtt) and an optional 'default' bool; materialised tracks carry a 'path'.
 */
class Subtitles
{
    /** ISO 639-1 -> 639-2/T, for the mp4 subtitle-stream language tag */
    private const array ISO_639_2 = [
        'en' => 'eng', 'es' => 'spa', 'pt' => 'por', 'de' => 'deu', 'fr' => 'fra',
        'ja' => 'jpn', 'it' => 'ita', 'nl' => 'nld', 'ru' => 'rus', 'zh' => 'zho',
        'ko' => 'kor', 'ar' => 'ara', 'tr' => 'tur', 'pl' => 'pol', 'id' => 'ind',
        'hi' => 'hin', 'uk' => 'ukr', 'fa' => 'fas',
    ];

    public static function enabled(): bool
    {
        return self::mode() !== 'off';
    }

    public static function shouldEmbed(): bool
    {
        return in_array(self::mode(), ['embed', 'both'], true);
    }

    public static function shouldSaveFile(): bool
    {
        return in_array(self::mode(), ['sidecar', 'both'], true);
    }

    public static function mode(): string
    {
        $value = strtolower(trim((string) ($_ENV['DOWNLOAD_SUBTITLES'] ?? '')));

        return match ($value) {
            'embed', 'true', '1', 'yes', 'on' => 'embed',
            'sidecar', 'file' => 'sidecar',
            'both' => 'both',
            default => 'off',
        };
    }

    /**
     * Apply the configured mode to an already-downloaded episode given its
     * materialised .vtt tracks (each ['path','language','default']). Embeds
     * and/or copies to subs/ per the mode, then removes the temp files.
     *
     * @param  array<int, array<string, mixed>>  $tracks
     */
    public static function deliver(string $filepath, array $tracks): void
    {
        $tracks = array_values(array_filter(
            $tracks,
            fn (array $track): bool => ! empty($track['path']) && file_exists($track['path'])
        ));

        if ($tracks === []) {
            return;
        }

        if (self::shouldEmbed() && self::embed($filepath, $tracks)) {
            Utils::writeln(sprintf('Embedded %d subtitle track(s)', count($tracks)));
        }

        if (self::shouldSaveFile()) {
            self::saveSidecars($filepath, $tracks);

            Utils::writeln('Saved subtitles to subs/');
        }

        foreach ($tracks as $track) {
            @unlink($track['path']);
        }
    }

    /**
     * Download HLS subtitle renditions (Mux) to temp .vtt files.
     *
     * @param  array<int, array<string, mixed>>  $tracks  each with 'url', 'language'
     * @return array<int, array<string, mixed>> materialised tracks with 'path'
     */
    public static function materializeHls(array $tracks): array
    {
        $out = [];

        foreach ($tracks as $track) {
            if (empty($track['url'])) {
                continue;
            }

            $lang = (string) ($track['language'] ?? 'en');
            $tmp = self::tempFile($lang);

            // allowed_extensions ALL: the HLS demuxer otherwise rejects Mux's
            // signed .vtt segment URLs (query string)
            $command = sprintf(
                'ffmpeg -y -hide_banner -loglevel error -allowed_extensions ALL -i %s %s 2>&1',
                escapeshellarg((string) $track['url']),
                escapeshellarg($tmp)
            );

            exec($command, $ignored, $code);

            if ($code === 0 && file_exists($tmp)) {
                $out[] = ['path' => $tmp, 'language' => $lang, 'default' => ! empty($track['default'])];
            } else {
                @unlink($tmp);
                Utils::writeln("Failed to fetch subtitles ($lang)");
            }
        }

        return $out;
    }

    /**
     * Download direct .vtt captions (Cloudflare) to temp files, forwarding the
     * login cookie when given.
     *
     * @param  array<int, array<string, mixed>>  $tracks  each with 'src', 'language'
     * @return array<int, array<string, mixed>> materialised tracks with 'path'
     */
    public static function materializeDirect(array $tracks, string $cookieHeader = ''): array
    {
        $client = new Client;
        $out = [];

        foreach ($tracks as $track) {
            if (empty($track['src'])) {
                continue;
            }

            $lang = (string) ($track['language'] ?? 'en');
            $tmp = self::tempFile($lang);

            try {
                $client->get((string) $track['src'], [
                    'headers' => $cookieHeader === '' ? [] : ['Cookie' => $cookieHeader],
                    'sink' => $tmp,
                    'verify' => false,
                ]);

                $out[] = ['path' => $tmp, 'language' => $lang, 'default' => ! empty($track['default'])];
            } catch (Throwable) {
                @unlink($tmp);
                Utils::writeln("Failed to fetch subtitles ($lang)");
            }
        }

        return $out;
    }

    /**
     * The 'subs' subfolder next to an episode file.
     */
    public static function subsDir(string $filepath): string
    {
        return dirname($filepath).DIRECTORY_SEPARATOR.'subs';
    }

    /**
     * Copy each materialised track into subs/ as <episode-stem>.<lang>.vtt.
     *
     * @param  array<int, array<string, mixed>>  $tracks
     */
    public static function saveSidecars(string $filepath, array $tracks): void
    {
        $dir = self::subsDir($filepath);

        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            Utils::writeln('Failed to create subs/ folder');

            return;
        }

        $base = pathinfo($filepath, PATHINFO_FILENAME);

        foreach ($tracks as $track) {
            if (empty($track['path']) || ! file_exists($track['path'])) {
                continue;
            }

            @copy($track['path'], $dir.DIRECTORY_SEPARATOR.$base.'.'.($track['language'] ?? 'en').'.vtt');
        }
    }

    /**
     * Embed the materialised .vtt tracks into the mp4 as mov_text subtitle
     * streams with a single in-place stream-copy remux. Best-effort.
     *
     * @param  array<int, array<string, mixed>>  $tracks
     */
    public static function embed(string $filepath, array $tracks): bool
    {
        if ($tracks === [] || ! file_exists($filepath)) {
            return false;
        }

        // escapeshellarg() on Windows mangles '!'/'%'; work on a safe sibling
        $input = $filepath;

        if (DIRECTORY_SEPARATOR === '\\' && strpbrk($filepath, '!%') !== false) {
            $input = dirname($filepath).DIRECTORY_SEPARATOR.'.lcdl-subs-tmp.mp4';

            if (! @rename($filepath, $input)) {
                Utils::write('Failed to embed subtitles: could not prepare a shell-safe path for '.basename($filepath));

                return false;
            }
        }

        $inputs = '-i '.escapeshellarg($input);
        $maps = '-map 0:v? -map 0:a?';
        $meta = '';

        foreach (array_values($tracks) as $i => $track) {
            $inputs .= ' -i '.escapeshellarg((string) $track['path']);
            $maps .= ' -map '.($i + 1).':0';
            $meta .= sprintf(' -metadata:s:s:%d language=%s', $i, escapeshellarg(self::iso6392((string) ($track['language'] ?? 'und'))));

            if (! empty($track['default'])) {
                $meta .= sprintf(' -disposition:s:%d default', $i);
            }
        }

        $tempOutput = $input.'.subs.mp4';

        // keep video/audio, carry chapters over, transcode the .vtt to mov_text
        $command = sprintf(
            'ffmpeg -y -hide_banner -loglevel error %s %s -map_chapters 0 -c copy -c:s mov_text%s %s 2>&1',
            $inputs,
            $maps,
            $meta,
            escapeshellarg($tempOutput)
        );

        $mtime = @filemtime($input);

        $output = [];
        $code = 0;

        exec($command, $output, $code);

        if ($code === 0 && @unlink($input)) {
            rename($tempOutput, $filepath);

            if ($mtime !== false) {
                @touch($filepath, $mtime);
            }

            return true;
        }

        @unlink($tempOutput);

        if ($input !== $filepath && file_exists($input) && ! file_exists($filepath)) {
            @rename($input, $filepath);
        }

        Utils::write('Failed to embed subtitles: '.implode(PHP_EOL, array_slice($output, -3)));

        return false;
    }

    private static function tempFile(string $lang): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'lc-sub-'.uniqid().'.'.$lang.'.vtt';
    }

    private static function iso6392(string $lang): string
    {
        $lang = strtolower($lang);

        return self::ISO_639_2[$lang] ?? $lang;
    }
}
