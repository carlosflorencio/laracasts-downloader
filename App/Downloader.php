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
 *
 * @package App
 */
class Downloader
{
    /**
     * Http resolver object
     *
     * @var Resolver
     */
    private $client;

    /**
     * System object
     *
     * @var SystemController
     */
    private $system;

    /**
     * Ubench lib
     *
     * @var Ubench
     */
    private $bench;

    // [string => number[]]
    private $filters = [];

    /**
     * @var LaracastsController
     */
    private $laracasts;

    /**
     * Receives dependencies
     *
     * @param HttpClient $httpClient
     * @param Filesystem $system
     * @param Ubench $bench
     * @param bool $retryDownload
     */
    public function __construct(HttpClient $httpClient, Filesystem $system, Ubench $bench, $retryDownload = false)
    {
        $this->client = new Resolver($httpClient, $bench, $retryDownload);
        $this->system = new SystemController($system);
        $this->bench = $bench;
        $this->laracasts = new LaracastsController($this->client);
    }

    /**
     * All the logic
     *
     * @param $options
     */
    public function start($options)
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

        $cachedData = $this->system->getCache();

        $onlineSeries = $this->laracasts->getSeries($cachedData, $this->filters);

        if (empty($this->filters))
            $this->system->setCache($onlineSeries);

        $this->bench->end();

        Utils::box('Downloading');

        $diff = Utils::compareLocalAndOnlineSeries($onlineSeries, $localSeries);

        $new_episodes = Utils::countEpisodes($diff);

        Utils::write(sprintf("%d new episodes. %s elapsed with %s of memory usage.",
                $new_episodes,
                $this->bench->getTime(),
                $this->bench->getMemoryUsage())
        );

        //Download Episodes
        if ($new_episodes > 0) {
            $this->downloadEpisodes($diff, $counter, $new_episodes);
        }

        Utils::writeln(sprintf("Finished! Downloaded %d new episodes. Failed: %d",
            $new_episodes - $counter['failed_episode'],
            $counter['failed_episode']
        ));
    }

    /**
     * Tries to login.
     *
     * @param string $email
     * @param string $password
     * @return bool
     * @throws LoginException
     */
    public function authenticate($email, $password)
    {
        Utils::box('Authenticating');

        if (empty($email) and empty($password)) {
            Utils::write("No EMAIL and PASSWORD is set in .env file");
            Utils::write("Browsing as guest and can only download free lessons.");

            return false;
        }

        $user = $this->client->login($email, $password);

        if (! is_null($user['error']))
            throw new LoginException($user['error']);

        if ($user['signedIn'])
            Utils::write("Logged in as " . $user['data']['email']);

        if (! $user['data']['subscribed']) {
            Utils::write("You don't have active subscription!");
            Utils::write("You can only download free lessons.");
        }

        return $user['signedIn'];
    }

    /**
     * Download Episodes
     *
     * @param $diff
     * @param $counter
     * @param $new_episodes
     */
    public function downloadEpisodes(&$diff, &$counter, $new_episodes)
    {
        $this->system->createFolderIfNotExists(SERIES_FOLDER);

        Utils::box('Downloading Series');

        foreach ($diff as $serie) {
            $this->system->createSerieFolderIfNotExists($serie['slug']);

            foreach ($serie['episodes'] as $episode) {

                if ($this->client->downloadEpisode($serie['slug'], $episode) === false) {
                    $counter['failed_episode'] = $counter['failed_episode'] + 1;
                }

                Utils::write(sprintf("Current: %d of %d total. Left: %d",
                    $counter['series']++,
                    $new_episodes,
                    $new_episodes - $counter['series'] + 1
                ));
            }
        }
    }

    protected function setFilters()
    {
        $short_options = "s:";
        $short_options .= 'e:';

        $long_options = array(
            "series-name:",
            "series-episodes:"
        );

        $options = getopt($short_options, $long_options);

        Utils::box(sprintf("Checking for options %s", json_encode($options)));

        if (count($options) == 0) {
            Utils::write('No options provided');
            return false;
        }

        $this->setSeriesFilter($options);

        $this->setEpisodesFilter($options);

        $diff = count($this->filters['episodes']) - count($this->filters['series']);

        $this->filters['episodes'] = array_merge(
            $this->filters['episodes'],
            array_fill(0, abs($diff), [])
        );

        $this->filters = array_combine(
            $this->filters['series'],
            $this->filters['episodes']
        );

        return true;
    }

    private function setSeriesFilter($options)
    {
        if (isset($options['s']) || isset($options['series-name'])) {
            $series = isset($options['s']) ? $options['s'] : $options['series-name'];

            if (! is_array($series))
                $series = [$series];

            $slugify = new Slugify();
            $slugify->addRule("'", '');

            $this->filters['series'] = array_map(function($serie) use ($slugify) {
                return $slugify->slugify($serie);
            }, $series);

            Utils::write(sprintf("Series names provided: %s", json_encode($this->filters['series'])));
        }
    }

    private function setEpisodesFilter($options)
    {
        $this->filters['episodes'] = [];

        if (isset($options['e']) || isset($options['series-episodes'])) {
            $episodes = isset($options['e']) ? $options['e'] : $options['series-episodes'];

            Utils::write(sprintf("Episode numbers provided: %s", json_encode($episodes)));

            if (! is_array($episodes)) {
                $episodes = [$episodes];
            }

            foreach ($episodes as $episode) {
                $positions = explode(',', $episode);

                sort($positions, SORT_NUMERIC);

                $this->filters['episodes'][] = $positions;
            }
        }
    }
}
