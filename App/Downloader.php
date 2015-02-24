<?php
/**
 * Main cycle of the app
 */
namespace App;

use App\Exceptions\LoginException;
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
                'lessons' => 1
            ];

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

            Utils::box('Authenticating');

            $this->doAuth($options);

            if ($new_lessons > 0) {
                $this->system->createFolderIfNotExists(LESSONS_FOLDER);
                Utils::box('Downloading Lessons');
                foreach ($diff['lessons'] as $lesson) {
                    $this->client->downloadLesson($lesson);
                    Utils::write(sprintf("Current: %d of %d total. Left: %d",
                        $counter['lessons']++,
                        $new_lessons,
                        $new_lessons - $counter['lessons'] + 1
                    ));
                }
            }

            if ($new_episodes > 0) {
                $this->system->createFolderIfNotExists(SERIES_FOLDER);
                Utils::box('Downloading Series');
                foreach ($diff['series'] as $serie => $episodes) {
                    $this->system->createSerieFolderIfNotExists($serie);
                    foreach ($episodes as $episode) {
                        $this->client->downloadSerieEpisode($serie, $episode);
                        Utils::write(sprintf("Current: %d of %d total. Left: %d",
                            $counter['series']++,
                            $new_episodes,
                            $new_episodes - $counter['series'] + 1
                        ));
                    }
                }
            }

            Utils::writeln(sprintf("Finished! %d new lessons and %d new episodes.",
                $new_lessons,
                $new_episodes
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
}
