<?php

/**
 * Moves subtitle sidecars (.vtt) that sit next to their videos into the
 * series' "subs" subfolder — the layout DOWNLOAD_SUBTITLES=sidecar/both now
 * uses. Earlier versions saved them next to the .mp4. The basename is kept
 * verbatim, so a file already named NN-Title.en.vtt simply lands in subs/.
 *
 * Usage (from the project root):
 *   php commands/MoveSubtitles.php --dry-run    # preview only
 *   php commands/MoveSubtitles.php              # apply
 *   php commands/MoveSubtitles.php -s slug      # one series
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

    // only .vtt directly in the series folder (subs/ and chapters/ are skipped
    // since glob does not recurse)
    $vtts = glob($dir.DIRECTORY_SEPARATOR.'*.vtt');

    if ($vtts === false || $vtts === []) {
        continue;
    }

    $subsDir = $dir.DIRECTORY_SEPARATOR.'subs';

    foreach ($vtts as $vtt) {
        $name = basename($vtt);
        $target = $subsDir.DIRECTORY_SEPARATOR.$name;

        if (file_exists($target)) {
            Utils::writeln("COLLISION $slug: $name already in subs/, skipped");
            $collisions++;

            continue;
        }

        Utils::writeln(($dryRun ? 'would move ' : 'move ')."$slug: $name -> subs/");

        if ($dryRun) {
            $moved++;

            continue;
        }

        if (! is_dir($subsDir) && ! mkdir($subsDir, 0777, true) && ! is_dir($subsDir)) {
            Utils::writeln("FAILED $slug: could not create subs/");
            $failed++;

            continue;
        }

        if (rename($vtt, $target)) {
            $moved++;
        } else {
            Utils::writeln("FAILED $slug: $name");
            $failed++;
        }
    }
}

Utils::writeln(
    ($dryRun ? '[dry-run] ' : 'Done. ')
    ."$moved moved, $collisions collisions skipped, $failed failed."
);
