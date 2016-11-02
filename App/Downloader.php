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

    /**
     * Receives dependencies
     *
     * @param Client $client
     * @param Filesystem $system
     * @param Ubench $bench
     */
    public function __construct(Client $client, Filesystem $system, Ubench $bench)
    {
        $this->client = new Resolver($client, $bench);
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
}
