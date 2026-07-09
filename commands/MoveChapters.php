<?php

/**
 * Consolidates chapter sidecars (.chapters.txt) into each series' "chapters"
 * subfolder — the layout DOWNLOAD_CHAPTERS=file/both now uses. Earlier
 * versions saved them next to the .mp4 (file mode) or in a "#merged"
 * subfolder (both mode); this moves both into chapters/ and removes the
 * emptied #merged folder. The basename is kept verbatim.
 *
 * Usage (from the project root):
 *   php commands/MoveChapters.php --dry-run    # preview only
 *   php commands/MoveChapters.php              # apply
 *   php commands/MoveChapters.php -s slug      # one series
 */

use App\Utils\Utils;

require_once __DIR__.'/../bootstrap.php';

$cliOptions = getopt('s:', ['dry-run']);

$slugFilter = $cliOptions['s'] ?? null;
$dryRun = array_key_exists('dry-run', $cliOptions);

$seriesPath = rtrim(BASE_FOLDER, '/\\').DIRECTORY_SEPARATOR.SERIES_FOLDER;

if (! is_dir($seriesPath)) {
    Utils::writeln("No series folder at $seriesPath");

    exit(1);
}

$moved = 0;
$collisions = 0;
$failed = 0;

foreach (scandir($seriesPath) as $slug) {
    if ($slug === '.' || $slug === '..') {
        continue;
    }

    if ($slugFilter !== null && $slug !== $slugFilter) {
        continue;
    }

    $dir = $seriesPath.DIRECTORY_SEPARATOR.$slug;

    if (! is_dir($dir)) {
        continue;
    }

    $mergedDir = $dir.DIRECTORY_SEPARATOR.'#merged';
    $chaptersDir = $dir.DIRECTORY_SEPARATOR.'chapters';

    // sidecars from the old #merged/ folder plus any sitting next to videos
    $sources = array_merge(
        is_dir($mergedDir) ? (glob($mergedDir.DIRECTORY_SEPARATOR.'*.chapters.txt') ?: []) : [],
        glob($dir.DIRECTORY_SEPARATOR.'*.chapters.txt') ?: []
    );

    foreach ($sources as $src) {
        $name = basename($src);
        $from = (dirname($src) === $mergedDir ? '#merged/' : '').$name;
        $target = $chaptersDir.DIRECTORY_SEPARATOR.$name;

        if (file_exists($target)) {
            // an episode can carry both a #merged/ and a next-to-video copy;
            // drop the redundant source when byte-identical, else flag it
            if (is_file($src) && md5_file($src) === md5_file($target)) {
                Utils::writeln(($dryRun ? 'would dedup ' : 'dedup ')."$slug: $from (identical to chapters/)");

                if (! $dryRun) {
                    @unlink($src);
                }

                $moved++;
            } else {
                Utils::writeln("COLLISION $slug: $from differs from chapters/ copy, skipped");
                $collisions++;
            }

            continue;
        }

        Utils::writeln(($dryRun ? 'would move ' : 'move ')."$slug: $from -> chapters/");

        if ($dryRun) {
            $moved++;

            continue;
        }

        if (! is_dir($chaptersDir) && ! mkdir($chaptersDir, 0777, true) && ! is_dir($chaptersDir)) {
            Utils::writeln("FAILED $slug: could not create chapters/");
            $failed++;

            continue;
        }

        if (rename($src, $target)) {
            $moved++;
        } else {
            Utils::writeln("FAILED $slug: $name");
            $failed++;
        }
    }

    // drop the now-empty #merged folder
    if (! $dryRun && is_dir($mergedDir)) {
        @rmdir($mergedDir);
    }
}

Utils::writeln(
    ($dryRun ? '[dry-run] ' : 'Done. ')
    ."$moved moved, $collisions collisions skipped, $failed failed."
);
