<?php

/**
 * Sets each downloaded episode's last-modified time to the lesson's
 * original publish date on laracasts.com (dateSegments.published — day
 * precision, normalised to 12:00 local). Useful after re-downloads,
 * which reset every file's timestamp to the download time.
 *
 * One request per series: the dates come from the series episode list
 * on the /episodes/1 page, not from each episode page.
 *
 * Usage (from the project root):
 *   php commands/BackfillTimestamps.php              # whole library
 *   php commands/BackfillTimestamps.php --dry-run    # preview only
 *   php commands/BackfillTimestamps.php -s slug      # one series
 *   php commands/BackfillTimestamps.php -s slug -e "1,5"
 */

use App\Html\Parser;
use App\Http\Resolver;
use App\Utils\Utils;
use GuzzleHttp\Client;

require_once __DIR__.'/../bootstrap.php';

$cliOptions = getopt('s:e:', ['dry-run']);

$slugFilter = $cliOptions['s'] ?? null;
$episodeFilter = isset($cliOptions['e']) ? array_map('intval', explode(',', (string) $cliOptions['e'])) : [];
$dryRun = array_key_exists('dry-run', $cliOptions);

$client = new Client(['base_uri' => LARACASTS_BASE_URL]);
$resolver = new Resolver($client, new Ubench);

$user = $resolver->login($options['email'], $options['password']);

if (empty($user['signedIn'])) {
    Utils::writeln('WARNING: login failed, continuing without authentication.');
}

$seriesPath = rtrim(BASE_FOLDER, '/\\').DIRECTORY_SEPARATOR.SERIES_FOLDER;

// group local videos by series so each slug costs a single request
$library = [];

foreach (glob($seriesPath.'/*/*.mp4') as $videoPath) {
    $slug = basename(dirname($videoPath));
    $number = (int) substr(basename($videoPath), 0, (int) strpos(basename($videoPath), '-'));

    if ($number < 1
        || ($slugFilter !== null && $slug !== $slugFilter)
        || ($episodeFilter !== [] && ! in_array($number, $episodeFilter, true))) {
        continue;
    }

    $library[$slug][$number] = $videoPath;
}

$touched = 0;
$current = 0;
$missing = 0;

foreach ($library as $slug => $videos) {
    try {
        $dates = Parser::getEpisodePublishDates($resolver->getHtml("series/$slug/episodes/1"));
    } catch (Exception $e) {
        Utils::writeln("$slug: ".$e->getMessage());
        $missing += count($videos);

        continue;
    }

    usleep(250000); // be gentle with laracasts.com

    foreach ($videos as $number => $videoPath) {
        $filename = basename($videoPath);
        $timestamp = isset($dates[$number]) ? strtotime($dates[$number].' 12:00:00') : false;

        if ($timestamp === false) {
            Utils::writeln("$slug/$filename: no publish date");
            $missing++;

            continue;
        }

        if (date('Y-m-d', filemtime($videoPath)) === date('Y-m-d', $timestamp)) {
            $current++;

            continue;
        }

        Utils::writeln(($dryRun ? 'would set ' : '')."$slug/$filename: ".date('Y-m-d', $timestamp));

        if (! $dryRun && ! touch($videoPath, $timestamp)) {
            Utils::writeln("FAILED $slug/$filename");

            continue;
        }

        $touched++;
    }
}

Utils::writeln(
    ($dryRun ? '[dry-run] ' : 'Done. ')
    ."$touched timestamps set, $current already correct, $missing without publish date."
);
