<?php

namespace App\Mux;

/**
 * Renders chapter markers as an ffmetadata file for ffmpeg's -map_chapters
 * https://ffmpeg.org/ffmpeg-formats.html#Metadata-2
 */
class ChapterMetadata
{
    /**
     * @param  array<int, array{title: string, start: int, end: int}>  $chapters  start/end in seconds
     */
    public static function build(array $chapters): string
    {
        $lines = [';FFMETADATA1'];

        foreach ($chapters as $chapter) {
            $lines[] = '';
            $lines[] = '[CHAPTER]';
            $lines[] = 'TIMEBASE=1/1000';
            $lines[] = 'START='.($chapter['start'] * 1000);
            $lines[] = 'END='.($chapter['end'] * 1000);
            $lines[] = 'title='.self::escape($chapter['title']);
        }

        return implode("\n", $lines)."\n";
    }

    public static function writeTempFile(array $chapters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lc-chapters-');

        file_put_contents($path, self::build($chapters));

        return $path;
    }

    /**
     * Sidecar path for an episode file
     * (e.g. 01-foo.mp4 -> 01-foo.chapters.txt).
     */
    public static function sidecarPath(string $filepath): string
    {
        return preg_replace('/\.[^.]+$/', '', $filepath).'.chapters.txt';
    }

    /**
     * Write the ffmetadata sidecar next to the episode file
     */
    public static function saveNextTo(string $filepath, array $chapters): string
    {
        $path = self::sidecarPath($filepath);

        file_put_contents($path, self::build($chapters));

        return $path;
    }

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
        return in_array(self::mode(), ['file', 'both'], true);
    }

    /**
     * DOWNLOAD_CHAPTERS env: 'embed' (or a plain boolean true) bakes the
     * markers into the mp4, 'file' saves an ffmetadata sidecar next to
     * the episode for manual ffmpeg merging, 'both' does both.
     */
    private static function mode(): string
    {
        $value = strtolower(trim((string) ($_ENV['DOWNLOAD_CHAPTERS'] ?? '')));

        return match ($value) {
            'embed', 'true', '1', 'yes', 'on' => 'embed',
            'file' => 'file',
            'both' => 'both',
            default => 'off',
        };
    }

    private static function escape(string $value): string
    {
        return addcslashes($value, "=;#\\\n");
    }
}
