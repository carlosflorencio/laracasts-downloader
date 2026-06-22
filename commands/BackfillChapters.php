<?php

/**
 * Writes .chapters.txt ffmetadata sidecars next to already-downloaded
 * episodes, built from each episode's transcript topics — no video
 * re-download. Merge one into its video with ffmpeg, e.g.:
 *
 *   ffmpeg -i "01-foo.mp4" -i "chapters/01-foo.chapters.txt" -map_chapters 1 -c copy "01-foo.chaptered.mp4"
 *
 * Usage (from the project root):
 *   php commands/BackfillChapters.php              # whole library
 *   php commands/BackfillChapters.php -s slug      # one series
 *   php commands/BackfillChapters.php -s slug -e "1,5"
 */

use App\Html\Parser;
use App\Http\Resolver;
use App\Mux\ChapterMetadata;
use App\Utils\Utils;
use GuzzleHttp\Client;

require_once __DIR__.'/../bootstrap.php';

$cliOptions = getopt('s:e:');

$slugFilter = $cliOptions['s'] ?? null;
$episodeFilter = isset($cliOptions['e']) ? array_map('intval', explode(',', (string) $cliOptions['e'])) : [];

$client = new Client(['base_uri' => LARACASTS_BASE_URL]);
$resolver = new Resolver($client, new Ubench);

$user = $resolver->login($options['email'], $options['password']);

if (empty($user['signedIn'])) {
    Utils::writeln('WARNING: login failed, continuing without authentication.');
}

$seriesPath = rtrim(BASE_FOLDER, '/\\').DIRECTORY_SEPARATOR.SERIES_FOLDER;

$written = 0;
$skipped = 0;
$missing = 0;

foreach (glob($seriesPath.'/*/*.mp4') as $videoPath) {
    $slug = basename(dirname($videoPath));
    $filename = basename($videoPath);
    $number = (int) substr($filename, 0, (int) strpos($filename, '-'));

    if ($number < 1
        || ($slugFilter !== null && $slug !== $slugFilter)
        || ($episodeFilter !== [] && ! in_array($number, $episodeFilter, true))) {
        continue;
    }

    if (file_exists(ChapterMetadata::chaptersSidecarPath($videoPath))) {
        $skipped++;

        continue;
    }

    try {
        $chapters = Parser::getEpisodeChapters($resolver->getHtml("series/$slug/episodes/$number"));
    } catch (Exception $e) {
        Utils::writeln("$slug/$filename: ".$e->getMessage());

        continue;
    }

    usleep(250000); // be gentle with laracasts.com

    if ($chapters === []) {
        Utils::writeln("$slug/$filename: no chapter data");
        $missing++;

        continue;
    }

    ChapterMetadata::saveToChapters($videoPath, $chapters);
    Utils::writeln("$slug/$filename: ".count($chapters).' chapters');
    $written++;
}

Utils::writeln("Done. $written written, $skipped already present, $missing without chapter data.");
