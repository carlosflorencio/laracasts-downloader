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

    private static function escape(string $value): string
    {
        return addcslashes($value, "=;#\\\n");
    }
}
