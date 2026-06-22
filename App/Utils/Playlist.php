<?php

namespace App\Utils;

/**
 * Generates a "#<slug>.m3u8" playlist listing a series' episode mp4s in
 * order (one bare filename per line) in the series folder. Toggled by
 * WRITE_PLAYLIST after downloads; --playlist-only (re)builds them for an
 * already-downloaded library.
 */
class Playlist
{
    public static function enabled(): bool
    {
        return filter_var($_ENV['WRITE_PLAYLIST'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Playlist basename for a series, e.g. '#blaze-deep-dive.m3u8'. The '#'
     * keeps it sorted to the top of the folder in file explorers.
     */
    public static function filename(string $serieSlug): string
    {
        return '#'.$serieSlug.'.m3u8';
    }

    /**
     * (Re)write the series playlist from the episode mp4s currently on disk,
     * ordered by their NN- number prefix. Returns false (writing nothing)
     * when the series folder holds no episodes.
     */
    public static function generate(string $serieSlug): bool
    {
        $dir = BASE_FOLDER.DIRECTORY_SEPARATOR.SERIES_FOLDER.DIRECTORY_SEPARATOR.$serieSlug;

        if (! is_dir($dir)) {
            return false;
        }

        // top-level episode mp4s only (the #merged subfolder is not scanned)
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.mp4');

        if ($files === false) {
            return false;
        }

        $episodes = [];

        foreach ($files as $file) {
            $name = basename($file);

            if (preg_match('/^(\d{2,})-/', $name, $matches)) {
                $episodes[(int) $matches[1]] = $name;
            }
        }

        if ($episodes === []) {
            return false;
        }

        ksort($episodes, SORT_NUMERIC);

        $playlist = $dir.DIRECTORY_SEPARATOR.self::filename($serieSlug);
        $eol = self::eol($playlist);

        return file_put_contents($playlist, implode($eol, $episodes).$eol) !== false;
    }

    /**
     * Preserve an existing playlist's line ending, defaulting to the
     * platform's for a new file.
     */
    private static function eol(string $playlist): string
    {
        if (is_file($playlist)) {
            $content = (string) file_get_contents($playlist);

            if (str_contains($content, "\r\n")) {
                return "\r\n";
            }

            if (str_contains($content, "\n")) {
                return "\n";
            }
        }

        return PHP_EOL;
    }
}
