<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Downloader;
use App\Exceptions\SubscriptionNotActiveException;
use App\Html\Parser;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Ubench;

/**
 * Class Resolver
 * @package App\Http
 */
class Resolver
{
    /**
     * Guzzle client
     * @var Client
     */
    private $client;

    /**
     * Guzzle cookie
     * @var CookieJar
     */
    private $cookie;

    /**
     * Ubench lib
     * @var Ubench
     */
    private $bench;

    /**
     * Receives dependencies
     *
     * @param Client $client
     * @param Ubench $bench
     */
    public function __construct(Client $client, Ubench $bench)
    {
        $this->client = $client;
        $this->cookie = new CookieJar();
        $this->bench = $bench;
    }

    /**
     * Grabs all lessons & series from the website.
     */
    public function getAllLessons()
    {
        $array = [];
        $html = $this->getAllPage();
        Parser::getAllLessons($html, $array);

        while ($nextPage = Parser::hasNextPage($html)) {
            $html = $this->client->get($nextPage)->getBody()->getContents();
            Parser::getAllLessons($html, $array);
        }

        return $array;
    }

    /**
     * Gets the latest lessons only.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getLatestLessons()
    {
        $array = [];

        $html = $this->getAllPage();
        Parser::getAllLessons($html, $array);

        return $array;
    }

    /**
     * Gets the html from the all page.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function getAllPage()
    {
        $response = $this->client->get(LARACASTS_ALL_PATH);

        return $response->getBody()->getContents();
    }

    /**
     * Tries to auth.
     *
     * @param $email
     * @param $password
     *
     * @return bool
     * @throws SubscriptionNotActiveException
     */
    public function doAuth($email, $password)
    {
        $response = $this->client->get(LARACASTS_LOGIN_PATH, [
            'cookies' => $this->cookie,
        ]);

        $token = Parser::getToken($response->getBody()->getContents());

        $response = $this->client->post(LARACASTS_POST_LOGIN_PATH, [
            'cookies' => $this->cookie,
            'body'    => [
                'email'    => $email,
                'password' => $password,
                '_token'   => $token,
                'remember' => 1,
            ],
        ]);

        $html = $response->getBody()->getContents();

        if (strpos($html, "Reactivate") !== FALSE) {
            throw new SubscriptionNotActiveException();
        }

        return strpos($html, "You are now logged in!") !== FALSE;
    }

    /**
     * Download the episode of the serie.
     *
     * @param $serie
     * @param $episode
     */
    public function downloadSerieEpisode($serie, $episode)
    {
        $path = LARACASTS_SERIES_PATH . '/' . $serie . '/episodes/' . $episode;
        $name = $this->getNameOfEpisode($path);
        $number = sprintf("%02d", $episode);
        $saveTo = BASE_FOLDER . '/' . SERIES_FOLDER . '/' . $serie . '/' . $number . '-' . $name . '.mp4';

        Utils::writeln(sprintf("Download started: %s . . . . Saving on " . SERIES_FOLDER . '/' . $serie . ' folder.',
            $number . ' - ' . $name
        ));
        $this->downloadLessonFromPath($name, $path, $saveTo);
    }

    /**
     * Downloads the lesson.
     *
     * @param $lesson
     */
    public function downloadLesson($lesson)
    {
        $path = LARACASTS_LESSONS_PATH . '/' . $lesson;
        $number = sprintf("%04d", ++Downloader::$currentLessonNumber);
        $saveTo = BASE_FOLDER . '/' . LESSONS_FOLDER . '/' . $number . '-' . $lesson . '.mp4';

        Utils::writeln(sprintf("Download started: %s . . . . Saving on " . LESSONS_FOLDER . ' folder.',
            $lesson
        ));
        $this->downloadLessonFromPath($lesson, $path, $saveTo);
    }

    /**
     * Helper to get the Location header.
     *
     * @param $url
     *
     * @return string
     */
    private function getRedirectUrl($url)
    {
        $response = $this->client->get($url, [
            'cookies'         => $this->cookie,
            'allow_redirects' => FALSE,
        ]);

        return $response->getHeader('Location');
    }

    /**
     * Gets the name of the serie episode.
     *
     * @param $path
     *
     * @return string
     */
    private function getNameOfEpisode($path)
    {
        $response = $this->client->get($path, ['cookies' => $this->cookie]);

        $name = Parser::getNameOfEpisode($response->getBody()->getContents());

        return Utils::parseEpisodeName($name);
    }

    /**
     * Helper to download the video.
     *
     * @param $name
     * @param $path
     * @param $saveTo
     */
    private function downloadLessonFromPath($name, $path, $saveTo)
    {
        $response = $this->client->get($path, ['cookies' => $this->cookie]);
        $downloadUrl = Parser::getDownloadLink($response->getBody()->getContents());

        $viemoUrl = $this->getRedirectUrl($downloadUrl);
        $finalUrl = $this->getRedirectUrl($viemoUrl);

        $this->bench->start();
        $this->client->get($finalUrl, [
            'save_to' => $saveTo,
        ]);
        $this->bench->end();

        Utils::write(sprintf("Elapsed time: %s, Memory: %s",
            $this->bench->getTime(),
            $this->bench->getMemoryUsage()
        ));
    }
}
