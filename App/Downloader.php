<?php namespace App;

use App\Http\Resolver;
use App\System\Controller;
use App\Utils\Utils;
use GuzzleHttp\Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Util;
use Ubench;

class Downloader
{
    /**
     * @var Resolver
     */
    private $client;

    /**
     * @var Controller
     */
    private $system;

    /**
     * @var Ubench
     */
    private $bench;

    /**
     * @var int
     */
    public static $currentLessonNumber;

    /**
     * @param Client $client
     * @param Filesystem $system
     * @param Ubench $bench
     */
    function __construct(Client $client, Filesystem $system, Ubench $bench)
    {
        $this->client = new Resolver($client, $bench);
        $this->system = new Controller($system);
        $this->bench = $bench;
    }

    public function start($options)
    {
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

        if($new_lessons > 0) {
            $this->system->createFolderIfNotExists(LESSONS_FOLDER);
            Utils::box('Downloading Lessons');
            foreach ($diff['lessons'] as $lesson) {
                $this->client->downloadLesson($lesson);
            }
        }

        if($new_episodes > 0) {
            $this->system->createFolderIfNotExists(SERIES_FOLDER);
            Utils::box('Downloading Series');
            foreach ($diff['series'] as $serie => $episodes) {
                $this->system->createSerieFolderIfNotExists($serie);
                foreach ($episodes as $episode) {
                    $this->client->downloadSerieEpisode($serie, $episode);
                }
            }
        }

        Utils::writeln(sprintf("Finished! %d new lessons and %d new episodes.",
            $new_lessons,
            $new_episodes
        ));
    }

    /**
     * Tries to login
     *
     * @param $options
     */
    public function doAuth($options)
    {
        if (!$this->client->doAuth($options['email'], $options['password'])) {
            Utils::write("Your login details are wrong!");
            die();
        }
        Utils::write("Successfull!");
    }

}