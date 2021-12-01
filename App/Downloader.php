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

    private $wantSeries = [];

    // this filters all online episodes to what the user has requested
    // this filter is applied before checking local (downloaded) videos,
    // so local videos exclusion  will work as before
    private $filterSeriesEpisodes = [];

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

        $this->bench->start();

        $localSeries = $this->system->getSeries();

        $cachedDate = $this->system->getCache();

        $onlineSeries = $this->laracasts->getSeries($cachedDate);

        $this->system->setCache($onlineSeries);

        $this->bench->end();

        if ($this->_haveOptions()) {  //filter all online lessons to the selected ones
            $onlineSeries = $this->onlyDownloadProvidedSeries($onlineSeries); //TODO: Check
        }

        Utils::box('Downloading');
        //Magic to get what to download
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

    //TODO: Bug: occurs when using double -e option
    protected function _haveOptions()
    {
        $found = false;

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

        $slugify = new Slugify();
        $slugify->addRule("'", '');

        if (isset($options['s']) || isset($options['series-name'])) {
            $series = isset($options['s']) ? $options['s'] : $options['series-name'];
            if (! is_array($series))
                $series = [$series];

            Utils::write(sprintf("Series names provided: %s", json_encode($series)));

            $this->wantSeries = array_map(function($serie) use ($slugify) {
                return $slugify->slugify($serie);
            }, $series);

            Utils::write(sprintf("Series names provided: %s", json_encode($this->wantSeries)));

            if (isset($options['e']) || isset($options['series-episodes'])) {
                $episodes = isset($options['e']) ? $options['e'] : $options['series-episodes'];

                Utils::write(sprintf("Episode numbers provided: %s", json_encode($episodes)));

                if (strpos($episodes, ',') === false) {
                    if (! is_array($episodes))
                        $episodes = [$episodes];
                } else {
                    $episodes = explode(',', $episodes);
                }

                sort($episodes, SORT_NUMERIC);

                $this->filterSeriesEpisodes = $episodes;

                Utils::write(sprintf("Episode numbers provided: %s", json_encode($this->filterSeriesEpisodes)));
            }

            $found = true;
        }

        return $found;
    }

    /**
     * Download selected Series
     *
     * @param $onlineSeries
     * @return array
     */
    public function onlyDownloadProvidedSeries($onlineSeries)
    {
        Utils::box('Checking if series exists');

        $selectedSeries = [];

        foreach ($this->wantSeries as $serieSlug) {
            if (isset($onlineSeries[$serieSlug])) {
                Utils::write('Series "' . $serieSlug . '" found!');

                $selectedSeries[$serieSlug] = $onlineSeries[$serieSlug];

                //TODO: Need to figure it how to handle it
                /*if (is_array($this->filterSeriesEpisodes) && count($this->filterSeriesEpisodes) > 0) {
                    $selectedSeries[$serieSlug] = $this->filterSeriesEpisodes;
                }*/
            } else {
                Utils::write("Series '" . $serieSlug . "' not found!");
            }
        }

        return $selectedSeries;
    }
}
