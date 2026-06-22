<?php

/**
 * Renames already-downloaded files so they follow the lesson titles in
 * cache.json, using the same sanitizer as new downloads
 * (Utils::parseEpisodeName). Fixes libraries written when the sanitizer
 * stripped characters that are legal on windows (commas, apostrophes,
 * dots, ...), which left .chapters.txt/.vtt sidecars orphaned from
 * their .mp4.
 *
 * Renames NN-*.mp4/.chapters.txt/.vtt files (also inside #merged/ and
 * subs/) whose name deviates from the canonical one, and updates renamed
 * entries in the series' #<slug>.m3u8 playlist.
 *
 * Usage (from the project root):
 *   php commands/ReconcileNames.php --dry-run    # preview only
 *   php commands/ReconcileNames.php              # apply
 *   php commands/ReconcileNames.php -s slug      # one series
 */

use App\Utils\Utils;

require_once __DIR__.'/../bootstrap.php';

$cliOptions = getopt('s:', ['dry-run']);

$slugFilter = $cliOptions['s'] ?? null;
$dryRun = array_key_exists('dry-run', $cliOptions);

$cacheFile = rtrim(BASE_FOLDER, '/\\').DIRECTORY_SEPARATOR.'cache.json';

if (! is_file($cacheFile)) {
    Utils::writeln("No cache.json at $cacheFile — run a sync first.");

    exit(1);
}

$cache = json_decode(file_get_contents($cacheFile), true);
$seriesPath = rtrim(BASE_FOLDER, '/\\').DIRECTORY_SEPARATOR.SERIES_FOLDER;

$renamed = 0;
$collisions = 0;
$playlists = 0;

foreach ($cache as $slug => $series) {
    if ($slugFilter !== null && $slug !== $slugFilter) {
        continue;
    }

    $dir = $seriesPath.DIRECTORY_SEPARATOR.$slug;

    if (! is_dir($dir)) {
        continue;
    }

    // canonical "NN-<sanitized title>" stem per episode number
    $canonical = [];

    foreach ($series['episodes'] as $episode) {
        $stem = Utils::parseEpisodeName($episode['title']);

        if ($stem !== null && $stem !== '') {
            $canonical[(int) $episode['number']] = sprintf('%02d', $episode['number']).'-'.$stem;
        }
    }

    $mp4Renames = []; // old basename => new basename, for the playlist rewrite

    foreach ([$dir, $dir.DIRECTORY_SEPARATOR.'#merged', $dir.DIRECTORY_SEPARATOR.'subs'] as $scanDir) {
        if (! is_dir($scanDir)) {
            continue;
        }

        foreach (scandir($scanDir) as $file) {
            if (! preg_match('/^(\d{2,})-/', $file, $prefix)
                || ! preg_match('/^(.*?)(\.chapters\.txt|(?:\.[a-zA-Z]{2,3}(?:-[A-Za-z0-9]+)?)?\.vtt|\.mp4)$/', $file, $parts)) {
                continue;
            }

            [, $stem, $suffix] = $parts;
            $number = (int) $prefix[1];

            if (! isset($canonical[$number]) || $stem === $canonical[$number]) {
                continue;
            }

            $target = $canonical[$number].$suffix;

            // on case-insensitive filesystems the "existing" target is the
            // source itself when only the casing changes — rename anyway
            if (strcasecmp($file, $target) !== 0
                && file_exists($scanDir.DIRECTORY_SEPARATOR.$target)) {
                Utils::writeln("COLLISION $slug: $file -> $target already exists, skipped");
                $collisions++;

                continue;
            }

            Utils::writeln(($dryRun ? 'would rename ' : 'rename ')."$slug: $file -> $target");

            if (! $dryRun && ! rename($scanDir.DIRECTORY_SEPARATOR.$file, $scanDir.DIRECTORY_SEPARATOR.$target)) {
                Utils::writeln("FAILED $slug: $file");

                continue;
            }

            $renamed++;

            if ($suffix === '.mp4' && $scanDir === $dir) {
                $mp4Renames[$file] = $target;
            }
        }
    }

    // keep the series playlist pointing at the renamed videos
    $playlist = $dir.DIRECTORY_SEPARATOR.'#'.$slug.'.m3u8';

    if ($mp4Renames === [] || ! is_file($playlist)) {
        continue;
    }

    $content = file_get_contents($playlist);
    $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";

    $lines = array_map(
        fn (string $line): string => $mp4Renames[trim($line)] ?? $line,
        explode($eol, $content)
    );

    if (! $dryRun) {
        file_put_contents($playlist, implode($eol, $lines));
    }

    Utils::writeln(($dryRun ? 'would update ' : 'update ')."$slug: ".basename($playlist));
    $playlists++;
}

Utils::writeln(
    ($dryRun ? '[dry-run] ' : 'Done. ')
    ."$renamed renamed, $playlists playlists updated, $collisions collisions skipped."
);
