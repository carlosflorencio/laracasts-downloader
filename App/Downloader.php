<?php
/**
 * Main cycle of the app
 */
namespace App;

use App\Exceptions\LoginException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Http\Resolver;
use App\System\Controller;
use App\Utils\Utils;
use Cocur\Slugify\Slugify;
use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use Ubench;

/**
 * Class Downloader
 * @package App
 */
class Downloader
{
    /**
     * Http resolver object
     * @var Resolver
     */
    private $client;

    /**
     * System object
     * @var Controller
     */
    private $system;

    /**
     * Ubench lib
     * @var Ubench
     */
    private $bench;

    /**
     * Number of local lessons
     * @var int
     */
    public static $totalLocalLessons;

    /**
     * Current lesson number
     * @var int
     */
    public static $currentLessonNumber;

    private $wantSeries = [];
    private $wantLessons = [];

    /**
     * Receives dependencies
     *
     * @param Client $client
     * @param Filesystem $system
     * @param Ubench $bench
     * @param bool $retryDownload
     */
    public function __construct(Client $client, Filesystem $system, Ubench $bench, $retryDownload = false)
    {
        $this->client = new Resolver($client, $bench, $retryDownload);
        $this->system = new Controller($system);
        $this->bench = $bench;
    }

    /**
     * All the logic
     *
     * @param $options
     */
    public function start($options)
    {
        try {
            $counter = [
                'series'  => 1,
                'lessons' => 1,
                'failed_episode' => 0,
                'failed_lesson' => 0
            ];

            Utils::box('Authenticating');

            $this->doAuth($options);

            Utils::box('Starting Collecting the data');

            $this->bench->start();

            $localLessons = $this->system->getAllLessons();
            $allLessonsOnline = $this->client->getAllLessons();

            $this->bench->end();


            if($this->_haveOptions()) {  //filter all online lessons to the selected ones
                $allLessonsOnline = $this->onlyDownloadProvidedLessonsAndSeries($allLessonsOnline);
            }

            Utils::box('Downloading');
            //Magic to get what to download
            $diff = Utils::resolveFaultyLessons($allLessonsOnline, $localLessons);

            $new_lessons = Utils::countLessons($diff);
            $new_episodes = Utils::countEpisodes($diff);

            Utils::write(sprintf("%d new lessons and %d episodes. %s elapsed with %s of memory usage.",
                    $new_lessons,
                    $new_episodes,
                    $this->bench->getTime(),
                    $this->bench->getMemoryUsage())
            );


            //Download Lessons
            if ($new_lessons > 0) {
                $this->downloadLessons($diff, $counter, $new_lessons);
            }

            //Donwload Episodes
            if ($new_episodes > 0) {
                $this->downloadEpisodes($diff, $counter, $new_episodes);
            }

            Utils::writeln(sprintf("Finished! Downloaded %d new lessons and %d new episodes. Failed: %d",
                $new_lessons - $counter['failed_lesson'],
                $new_episodes - $counter['failed_episode'],
                $counter['failed_lesson'] + $counter['failed_episode']
            ));
        } catch (LoginException $e) {
            Utils::write("Your login details are wrong!");
        } catch (SubscriptionNotActiveException $e) {
            Utils::write('Your subscription is not active!');
        }
    }

    /**
     * Tries to login.
     *
     * @param $options
     *
     * @throws \Exception
     */
    public function doAuth($options)
    {
        if (!$this->client->doAuth($options['email'], $options['password'])) {
            throw new LoginException("Can't do the login..");
        }
        Utils::write("Successfull!");
    }

    /**
     * Download Lessons
     * @param $diff
     * @param $counter
     * @param $new_lessons
     */
    public function downloadLessons(&$diff, &$counter, $new_lessons)
    {
        $this->system->createFolderIfNotExists(LESSONS_FOLDER);
        Utils::box('Downloading Lessons');
        foreach ($diff['lessons'] as $lesson) {

            if($this->client->downloadLesson($lesson) === false) {
                $counter['failed_lesson']++;
            }

            Utils::write(sprintf("Current: %d of %d total. Left: %d",
                $counter['lessons']++,
                $new_lessons,
                $new_lessons - $counter['lessons'] + 1
            ));
        }
    }

    /**
     * Download Episodes
     * @param $diff
     * @param $counter
     * @param $new_episodes
     */
    public function downloadEpisodes(&$diff, &$counter, $new_episodes)
    {
        $this->system->createFolderIfNotExists(SERIES_FOLDER);
        Utils::box('Downloading Series');
        foreach ($diff['series'] as $serie => $episodes) {
            $this->system->createSerieFolderIfNotExists($serie);
            foreach ($episodes as $episode) {

                if($this->client->downloadSerieEpisode($serie, $episode) === false) {
                    $counter['failed_episode'] = $counter['failed_episode'] +1;
                }

                Utils::write(sprintf("Current: %d of %d total. Left: %d",
                    $counter['series']++,
                    $new_episodes,
                    $new_episodes - $counter['series'] + 1
                ));
            }
        }
    }

    protected function _haveOptions()
    {
        $found = false;

        $short_options = "s:";
        $short_options .= "l:";

        $long_options  = array(
            "series-name:",
            "lesson-name:"
        );
        $options = getopt($short_options, $long_options);

        Utils::box(sprintf("Checking for options %s", json_encode($options)));

        if(count($options) == 0) {
            Utils::write('No options provided');
            return false;
        }

        $slugify = new Slugify();
        $slugify->addRule("'", '');

        if(isset($options['s']) || isset($options['series-name'])) {
            $series = isset($options['s']) ? $options['s'] : $options['series-name'];
            if(!is_array($series))
                $series = [$series];

            Utils::write(sprintf("Series names provided: %s", json_encode($series)));


            $this->wantSeries = array_map(function ($serie) use ($slugify) {
                return $slugify->slugify($serie);
            }, $series);

            Utils::write(sprintf("Series names provided: %s", json_encode($this->wantSeries)));

            $found = true;
        }

        if(isset($options['l']) || isset($options['lesson-name'])) {
            $lessons = isset($options['l']) ? $options['l'] : $options['lesson-name'];

            if(!is_array($lessons))
                $lessons = [$lessons];

            Utils::write(sprintf("Lesson names provided: %s", json_encode($lessons)));

            $this->wantLessons = array_map(function($lesson) use ($slugify) {
                return $slugify->slugify($lesson); },$lessons
            );

            Utils::write(sprintf("Lesson names provided: %s", json_encode($this->wantLessons)));

            $found = true;
        }

        return $found;
    }

    /**
     * Download selected Series and lessons
     * @param $allLessonsOnline
     * @return array
     */
    public function onlyDownloadProvidedLessonsAndSeries($allLessonsOnline)
    {
        Utils::box('Checking if series and lessons exists');

        $selectedLessonsOnline = [
            'lessons' => [],
            'series' => []
        ];

        foreach($this->wantSeries as $series) {
            if(isset($allLessonsOnline['series'][$series])) {
                Utils::write('Series "'.$series.'" found!');
                $selectedLessonsOnline['series'][$series] = $allLessonsOnline['series'][$series];
            } else {
                Utils::write("Series '".$series."' not found!");
            }
        }

        foreach($this->wantLessons as $lesson) {
            if(in_array($lesson, $allLessonsOnline['lessons'])) {
                Utils::write('Lesson "'.$lesson.'" found');
                $selectedLessonsOnline['lessons'][] = $allLessonsOnline['lessons'][array_search($lesson, $allLessonsOnline)];
            } else {
                Utils::write("Lesson '".$lesson."' not found!");
            }
        }

        return $selectedLessonsOnline;
    }
}
