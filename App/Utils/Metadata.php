<?php

namespace App\Utils;

/**
 * Writes container metadata tags into an episode mp4 with a single
 * ffmpeg stream-copy remux (no re-encoding). Toggled by WRITE_METADATA.
 */
class Metadata
{
    public static function enabled(): bool
    {
        return filter_var($_ENV['WRITE_METADATA'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Tag the mp4 in place. Best-effort: returns false (and leaves the
     * original untouched) when there is nothing to write or ffmpeg fails.
     *
     * @param  array<string, string>  $tags  e.g. ['title' => ..., 'album' => ..., 'track' => ...]
     */
    public static function write(string $filepath, array $tags): bool
    {
        $tags = array_filter($tags, fn (string $value): bool => trim($value) !== '');

        if ($tags === [] || ! file_exists($filepath)) {
            return false;
        }

        $metadata = '';

        foreach ($tags as $key => $value) {
            $metadata .= ' -metadata '.escapeshellarg("$key=$value");
        }

        $tempOutput = $filepath.'.metadata.mp4';

        // keep the real streams (video/audio/subtitle) and drop the HLS
        // timed-metadata data stream; chapters are copied by default
        $command = sprintf(
            'ffmpeg -y -hide_banner -loglevel error -i %s -map 0:v? -map 0:a? -map 0:s? -c copy%s %s 2>&1',
            escapeshellarg($filepath),
            $metadata,
            escapeshellarg($tempOutput)
        );

        // preserve the original mtime (publish date) across the remux
        $mtime = @filemtime($filepath);

        $output = [];
        $code = 0;

        exec($command, $output, $code);

        if ($code === 0 && @unlink($filepath)) {
            rename($tempOutput, $filepath);

            if ($mtime !== false) {
                @touch($filepath, $mtime);
            }

            return true;
        }

        @unlink($tempOutput);

        Utils::write('Failed to write metadata: '.implode(PHP_EOL, array_slice($output, -3)));

        return false;
    }
}
