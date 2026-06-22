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

        // PHP's escapeshellarg() on Windows replaces '!' and '%' with spaces
        // (to neutralise cmd.exe variable expansion), mangling both the file
        // path and any tag values that contain them. Rename the source to a
        // shell-safe sibling first (PHP rename, no shell) and feed the tag
        // values through an ffmetadata side file instead of inline -metadata
        // args, so neither path nor values ever pass through the shell raw.
        $input = $filepath;

        if (DIRECTORY_SEPARATOR === '\\' && strpbrk($filepath, '!%') !== false) {
            $input = dirname($filepath).DIRECTORY_SEPARATOR.'.lcdl-metadata-tmp.mp4';

            if (! @rename($filepath, $input)) {
                Utils::write('Failed to write metadata: could not prepare a shell-safe path for '.basename($filepath));

                return false;
            }
        }

        $metadataFile = self::writeTempFile($tags);
        $tempOutput = $input.'.metadata.mp4';

        // read the tags from an ffmetadata side input (keeps '!'/'%' intact),
        // keep the real streams while dropping the HLS timed-metadata data
        // stream, and preserve any chapters already embedded in the source
        $command = sprintf(
            'ffmpeg -y -hide_banner -loglevel error -i %s -f ffmetadata -i %s -map 0:v? -map 0:a? -map 0:s? -map_metadata 1 -map_chapters 0 -c copy %s 2>&1',
            escapeshellarg($input),
            escapeshellarg($metadataFile),
            escapeshellarg($tempOutput)
        );

        // preserve the original mtime (publish date) across the remux
        $mtime = @filemtime($input);

        $output = [];
        $code = 0;

        exec($command, $output, $code);

        @unlink($metadataFile);

        if ($code === 0 && @unlink($input)) {
            rename($tempOutput, $filepath);

            if ($mtime !== false) {
                @touch($filepath, $mtime);
            }

            return true;
        }

        @unlink($tempOutput);

        // restore the original filename if we renamed it for a failed run
        if ($input !== $filepath && file_exists($input) && ! file_exists($filepath)) {
            @rename($input, $filepath);
        }

        Utils::write('Failed to write metadata: '.implode(PHP_EOL, array_slice($output, -3)));

        return false;
    }

    /**
     * Render the global tags as an ffmetadata file in the system temp dir and
     * return its (shell-safe) path.
     *
     * @param  array<string, string>  $tags
     */
    private static function writeTempFile(array $tags): string
    {
        $lines = [';FFMETADATA1'];

        foreach ($tags as $key => $value) {
            $lines[] = $key.'='.self::escape((string) $value);
        }

        $path = tempnam(sys_get_temp_dir(), 'lc-metadata-');

        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }

    /**
     * Escape the ffmetadata-special characters per the spec.
     * https://ffmpeg.org/ffmpeg-formats.html#Metadata-2
     */
    private static function escape(string $value): string
    {
        return addcslashes($value, "=;#\\\n");
    }
}
