<?php

/**
 * Main cycle of the app
 */

namespace App;

use App\Exceptions\LoginException;
use App\Http\Resolver;
use App\Laracasts\Controller as LaracastsController;
use App\System\Controller as SystemController;
use App\Utils\Utils;
use Cocur\Slugify\Slugify;
use Exception;
use GuzzleHttp\Client as HttpClient;
use League\Flysystem\Filesystem;
use Ubench;

/**
 * Class Downloader
 */
class Downloader
{
    private readonly Resolver $client;

    private readonly SystemController $system;

    private readonly Ubench $bench;

    /** @array<string, int[]> */
    private array $filters = [];

    private readonly LaracastsController $laracasts;

    /** @var bool Don't scrap pages and only get from existing cache */
    private bool $cacheOnly = false;

    /** @var bool Only fetch chapter sidecars, no videos */
    private bool $chaptersOnly = false;

    /** @var bool Only fetch subtitles, no videos */
    private bool $subtitlesOnly = false;

    /** @var bool Only fix episode file timestamps, no videos */
    private bool $timestampsOnly = false;

    /** @var bool Only (re)write metadata of downloaded videos, no videos */
    private bool $metadataOnly = false;

    public function __construct(HttpClient $httpClient, Filesystem $system, Ubench $bench)
    {
        $this->client = new Resolver($httpClient, $bench);
        $this->system = new SystemController($system);
        $this->bench = $bench;
        $this->laracasts = new LaracastsController($this->client);
    }

    public function start(array $options): void
    {
        $counter = [
            'series' => 1,
            'failed_episode' => 0,
        ];

        $this->authenticate($options['email'], $options['password']);

        Utils::box('Starting Collecting the data');

        $this->setFilters();

        // metadata-only backfills already-downloaded videos; with no -s it
        // walks the whole local library, optionally narrowed by -s/-e
        if ($this->metadataOnly) {
            $this->updateMetadata();

            return;
        }

        $onlyMode = $this->chaptersOnly || $this->subtitlesOnly || $this->timestampsOnly;

        if ($onlyMode && $this->filters === []) {
            throw new Exception('--chapters-only/--subtitles-only/--timestamps-only requires a series filter, e.g. -s "series-slug"');
        }

        $this->bench->start();

        // the *-only modes process every filtered episode regardless of which exist locally
        $localSeries = $onlyMode ? [] : $this->system->getSeries();

        if ($this->filters === []) {
            $cachedData = $this->system->getCache();

            $onlineSeries = $this->laracasts->getSeries($cachedData, $this->cacheOnly);

            $this->system->setCache($onlineSeries);
        } else {
            $onlineSeries = $this->laracasts->getFilteredSeries($this->filters);
        }

        $this->bench->end();

        Utils::box('Downloading');

        $newEpisodes = Utils::compareLocalAndOnlineSeries($onlineSeries, $localSeries);

        $newEpisodesCount = Utils::countEpisodes($newEpisodes);

        Utils::write(
            sprintf(
                '%d new episodes. %s elapsed with %s of memory usage.',
                $newEpisodesCount,
                $this->bench->getTime(),
                $this->bench->getMemoryUsage()
            )
        );

        if ($newEpisodesCount > 0) {
            $this->downloadEpisodes($newEpisodes, $counter, $newEpisodesCount);
        }

        Utils::writeln(
            sprintf(
                'Finished! Downloaded %d new episodes. Failed: %d',
                $newEpisodesCount - $counter['failed_episode'],
                $counter['failed_episode']
            )
        );
    }

    /**
     * Backfill metadata into already-downloaded videos. Walks the local
     * library (optionally narrowed by -s/-e), pulling the series title from
     * cache (falling back to a one-off page fetch) and lesson titles from
     * cache (falling back to the on-disk filename).
     */
    private function updateMetadata(): void
    {
        Utils::box('Updating metadata');

        $localSeries = $this->system->getSeries();
        $cache = $this->system->getCache();

        $slugs = $this->filters === [] ? array_keys($localSeries) : array_keys($this->filters);

        $updated = 0;
        $failed = 0;

        foreach ($slugs as $slug) {
            if (! isset($localSeries[$slug])) {
                Utils::writeln("Not downloaded, skipping series: $slug");

                continue;
            }

            $seriesTitle = $cache[$slug]['title'] ?? $this->client->fetchSeriesTitle($slug);

            $lessonTitles = [];

            foreach ($cache[$slug]['episodes'] ?? [] as $episode) {
                $lessonTitles[(int) $episode['number']] = $episode['title'];
            }

            $episodeFilter = $this->filters[$slug] ?? [];

            foreach ($localSeries[$slug] as $number) {
                if ($episodeFilter !== [] && ! in_array($number, $episodeFilter)) {
                    continue;
                }

                if ($this->client->updateEpisodeMetadata($slug, $number, $lessonTitles[$number] ?? null, $seriesTitle)) {
                    $updated++;
                } else {
                    $failed++;
                }
            }
        }

        Utils::writeln(sprintf('Finished! Metadata written for %d videos. Failed: %d', $updated, $failed));
    }

    /**
     * Tries to login.
     *
     * @return bool
     *
     * @throws LoginException
     */
    public function authenticate(string $email, string $password)
    {
        Utils::box('Authenticating');

        if ($email === '' || $password === '') {
            throw new LoginException('No EMAIL and PASSWORD is set in .env file');
        }

        $user = $this->client->login($email, $password);

        if (! is_null($user['error'])) {
            throw new LoginException($user['error']);
        }

        if ($user['signedIn']) {
            Utils::write('Logged in as '.$user['data']['email']);
        }

        // Let's allow user with no subscription to download free lessons
        // https://github.com/carlosflorencio/laracasts-downloader/issues/131
        /*
        if (! $user['data']['subscribed']) {
            throw new LoginException("You don't have active subscription!");
        }
        */
        return $user['signedIn'];
    }

    /**
     * Download Episodes
     */
    public function downloadEpisodes($newEpisodes, array &$counter, $newEpisodesCount): void
    {
        $this->system->createFolderIfNotExists(SERIES_FOLDER);

        Utils::box('Downloading Series');

        foreach ($newEpisodes as $serie) {
            $this->system->createSerieFolderIfNotExists($serie['slug']);

            foreach ($serie['episodes'] as $episode) {

                if ($this->downloadEpisodeAssets($serie['slug'], $episode) === false) {
                    $counter['failed_episode'] += 1;
                }

                Utils::write(
                    sprintf(
                        'Current: %d of %d total. Left: %d              ',
                        $counter['series']++,
                        $newEpisodesCount,
                        $newEpisodesCount - $counter['series'] + 1
                    )
                );
            }
        }
    }

    /**
     * Download the episode video, or only its chapter/subtitle side
     * files / timestamp fix when the matching --*-only flags are set.
     */
    private function downloadEpisodeAssets(string $serieSlug, array $episode): bool
    {
        if (! $this->chaptersOnly && ! $this->subtitlesOnly && ! $this->timestampsOnly) {
            return $this->client->downloadEpisode($serieSlug, $episode);
        }

        $result = true;

        if ($this->chaptersOnly) {
            $result = $this->client->downloadEpisodeChapters($serieSlug, $episode);
        }

        if ($this->subtitlesOnly) {
            $result = $this->client->downloadEpisodeSubtitles($serieSlug, $episode) && $result;
        }

        if ($this->timestampsOnly) {
            $result = $this->client->setEpisodeTimestamp($serieSlug, $episode) && $result;
        }

        return $result;
    }

    protected function setFilters(): bool
    {
        $shortOptions = 's:';
        $shortOptions .= 'e:';

        $longOptions = [
            'series-name:',
            'series-episodes:',
            'cache-only',
            'chapters-only',
            'subtitles-only',
            'timestamps-only',
            'metadata-only',
        ];

        $options = getopt($shortOptions, $longOptions);

        if (array_key_exists('cache-only', $options)) {
            $this->cacheOnly = true;
            unset($options['cache-only']);
        }

        if (array_key_exists('chapters-only', $options)) {
            $this->chaptersOnly = true;
            unset($options['chapters-only']);
        }

        if (array_key_exists('subtitles-only', $options)) {
            $this->subtitlesOnly = true;
            unset($options['subtitles-only']);
        }

        if (array_key_exists('timestamps-only', $options)) {
            $this->timestampsOnly = true;
            unset($options['timestamps-only']);
        }

        if (array_key_exists('metadata-only', $options)) {
            $this->metadataOnly = true;
            unset($options['metadata-only']);
        }

        Utils::box(sprintf('Checking for options %s', json_encode($options)));

        if (count($options) == 0) {
            Utils::write('No options provided');

            return false;
        }

        $this->setSeriesFilter($options);

        $this->setEpisodesFilter($options);

        $newEpisodes = count($this->filters['episodes']) - count($this->filters['series']);

        $this->filters['episodes'] = array_merge(
            $this->filters['episodes'],
            array_fill(0, abs($newEpisodes), [])
        );

        $this->filters = array_combine(
            $this->filters['series'],
            $this->filters['episodes']
        );

        return true;
    }

    private function setSeriesFilter($options): void
    {
        if (isset($options['s']) || isset($options['series-name'])) {
            $series = $options['s'] ?? $options['series-name'];

            if (! is_array($series)) {
                $series = [$series];
            }

            $slugify = new Slugify;
            $slugify->addRule("'", '');

            $this->filters['series'] = array_map(fn ($serie): string => $slugify->slugify($serie), $series);

            Utils::write(sprintf('Series names provided: %s', json_encode($this->filters['series'])));
        }
    }

    private function setEpisodesFilter($options): void
    {
        $this->filters['episodes'] = [];

        if (isset($options['e']) || isset($options['series-episodes'])) {
            $episodes = $options['e'] ?? $options['series-episodes'];

            Utils::write(sprintf('Episode numbers provided: %s', json_encode($episodes)));

            if (! is_array($episodes)) {
                $episodes = [$episodes];
            }

            foreach ($episodes as $episode) {
                $positions = explode(',', (string) $episode);

                sort($positions, SORT_NUMERIC);

                $this->filters['episodes'][] = $positions;
            }
        }
    }
}
