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
use GuzzleHttp\Client as HttpClient;
use League\Flysystem\Filesystem;
use Ubench;

/**
 * Class Downloader
 */
class Downloader
{
    private readonly Resolver $client;

    private readonly \App\System\Controller $system;

    private readonly Ubench $bench;

    /** @array<string, int[]> */
    private array $filters = [];

    private readonly LaracastsController $laracasts;

    /** @var bool Don't scrap pages and only get from existing cache */
    private bool $cacheOnly = false;

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

        $this->bench->start();

        $localSeries = $this->system->getSeries();

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

                if ($this->client->downloadEpisode($serie['slug'], $episode) === false) {
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

    protected function setFilters(): bool
    {
        $shortOptions = 's:';
        $shortOptions .= 'e:';
        $shortOptions .= 'l:';

        $longOptions = [
            'series-name:',
            'series-episodes:',
            'cache-only',
            'sub-langs:',
        ];

        $options = getopt($shortOptions, $longOptions);

        if (array_key_exists('cache-only', $options)) {
            $this->cacheOnly = true;
            unset($options['cache-only']);
        }

        if (isset($options['l']) || isset($options['sub-langs'])) {
            $subLangs = $options['l'] ?? $options['sub-langs'];
            $_ENV['SUB_LANGS'] = $subLangs;
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
