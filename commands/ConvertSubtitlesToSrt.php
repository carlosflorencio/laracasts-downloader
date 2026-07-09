<?php

/**
 * Converts the subtitle sidecars in each series' "subs" subfolder from WebVTT
 * (.vtt) to SubRip (.srt). Players such as VLC only language-label external
 * sidecars from the filename when they are .srt, never .vtt, so this brings an
 * older library in line with the current saveSidecars() output. The basename
 * (and its .<lang> token) is kept verbatim; the source .vtt is removed once the
 * .srt is written. ffmpeg must be on PATH.
 *
 * Usage (from the project root):
 *   php commands/ConvertSubtitlesToSrt.php --dry-run   # preview only
 *   php commands/ConvertSubtitlesToSrt.php             # apply
 *   php commands/ConvertSubtitlesToSrt.php -s slug     # one series
 */

use App\Utils\Subtitles;
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

$converted = 0;
$collisions = 0;
$failed = 0;

foreach (scandir($seriesPath) as $slug) {
    if ($slug === '.' || $slug === '..') {
        continue;
    }

    if ($slugFilter !== null && $slug !== $slugFilter) {
        continue;
    }

    $subsDir = $seriesPath.DIRECTORY_SEPARATOR.$slug.DIRECTORY_SEPARATOR.'subs';

    if (! is_dir($subsDir)) {
        continue;
    }

    $vtts = glob($subsDir.DIRECTORY_SEPARATOR.'*.vtt');

    if ($vtts === false || $vtts === []) {
        continue;
    }

    foreach ($vtts as $vtt) {
        $name = basename($vtt);
        $srt = substr($vtt, 0, -4).'.srt';

        if (file_exists($srt)) {
            Utils::writeln("COLLISION $slug: ".basename($srt).' already exists, skipped');
            $collisions++;

            continue;
        }

        Utils::writeln(($dryRun ? 'would convert ' : 'convert ')."$slug: $name -> ".basename($srt));

        if ($dryRun) {
            $converted++;

            continue;
        }

        if (Subtitles::vttToSrt($vtt, $srt)) {
            @unlink($vtt);
            $converted++;
        } else {
            Utils::writeln("FAILED $slug: $name");
            $failed++;
        }
    }
}

Utils::writeln(
    ($dryRun ? '[dry-run] ' : 'Done. ')
    ."$converted converted, $collisions collisions skipped, $failed failed."
);
