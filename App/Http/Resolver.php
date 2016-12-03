<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Downloader;
use App\Exceptions\NoDownloadLinkException;
use App\Exceptions\SubscriptionNotActiveException;
use App\Html\Parser;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Event\ProgressEvent;
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
     * Retry download on connection fail
     * @var int
     */
    private $retryDownload = false;

    /**
     * Receives dependencies
     *
     * @param Client $client
     * @param Ubench $bench
     * @param bool $retryDownload
     */
    public function __construct(Client $client, Ubench $bench, $retryDownload = false)
    {
        $this->client = $client;
        $this->cookie = new CookieJar();
        $this->bench = $bench;
        $this->retryDownload = $retryDownload;
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
            $html = $this->client->get($nextPage, ['verify' => false])->getBody()->getContents();
            Parser::getAllLessons($html, $array);
        }

        Downloader::$currentLessonNumber = count($array['lessons']);

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
        $response = $this->client->get(LARACASTS_ALL_PATH, ['verify' => false]);

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
            'verify' => false
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
            'verify' => false
        ]);

        $html = $response->getBody()->getContents();

        if (strpos($html, "Reactivate") !== FALSE) {
            throw new SubscriptionNotActiveException();
        }

        if(strpos($html, "The email must be a valid email address.") !== FALSE) {
            return false;
        }

        return strpos($html, "verify your credentials.") === FALSE;
    }

    /**
     * Download the episode of the serie.
     *
     * @param $serie
     * @param $episode
     * @return bool
     */
    public function downloadSerieEpisode($serie, $episode)
    {
        $path = LARACASTS_SERIES_PATH . '/' . $serie . '/episodes/' . $episode;
        $episodePage = $this->getPage($path);
        $name = $this->getNameOfEpisode($episodePage, $path);
        $number = sprintf("%02d", $episode);
        $saveTo = BASE_FOLDER . '/' . SERIES_FOLDER . '/' . $serie . '/' . $number . '-' . $name . '.mp4';
        Utils::writeln(sprintf("Download started: %s . . . . Saving on " . SERIES_FOLDER . '/' . $serie . ' folder.',
            $number . ' - ' . $name
        ));

        return $this->downloadLessonFromPath($episodePage, $saveTo);
    }

    /**
     * Downloads the lesson.
     *
     * @param $lesson
     * @return bool
     */
    public function downloadLesson($lesson)
    {
        $path = LARACASTS_LESSONS_PATH . '/' . $lesson;
        $number = sprintf("%04d", Downloader::$totalLocalLessons + Downloader::$currentLessonNumber--);
        $saveTo = BASE_FOLDER . '/' . LESSONS_FOLDER . '/' . $number . '-' . $lesson . '.mp4';

        Utils::writeln(sprintf("Download started: %s . . . . Saving on " . LESSONS_FOLDER . ' folder.',
            $lesson
        ));
        $html = $this->getPage($path);
        return $this->downloadLessonFromPath($html, $saveTo);
    }

    /**
     * Helper function to get html of a page
     * @param $path
     * @return string
     */
    private function getPage($path) {
        return $this->client
            ->get($path, ['cookies' => $this->cookie, 'verify' => false])
            ->getBody()
            ->getContents();
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
            'verify' => false
        ]);

        return $response->getHeader('Location');
    }

    /**
     * Gets the name of the serie episode.
     *
     * @param $html
     *
     * @param $path
     * @return string
     */
    private function getNameOfEpisode($html, $path)
    {
        $name = Parser::getNameOfEpisode($html, $path);

        return Utils::parseEpisodeName($name);
    }

    /**
     * Helper to download the video.
     *
     * @param $html
     * @param $saveTo
     * @return bool
     */
    private function downloadLessonFromPath($html, $saveTo)
    {
        try {
            $downloadUrl = Parser::getDownloadLink($html);
            $viemoUrl = $this->getRedirectUrl($downloadUrl);
            $finalUrl = $this->getRedirectUrl($viemoUrl);
        } catch(NoDownloadLinkException $e) {
            Utils::write(sprintf("Can't download this lesson! :( No download button"));

            try {
                Utils::write(sprintf("Tring to find a Wistia.net video"));
                $Wistia = new Wistia($html,$this->bench);
                $finalUrl = $Wistia->getDownloadUrl();
            } catch(NoDownloadLinkException $e) {
                return false;
            }

        }

        $this->bench->start();

        $req = $this->client->createRequest('GET', $finalUrl, [
            'save_to' => $saveTo,
            'verify' => false
        ]);

        if (php_sapi_name() == "cli") { //on cli show progress
            $req->getEmitter()->on('progress', function (ProgressEvent $e) {
                printf("> Total: %d%% Downloaded: %s of %s     \r",
                    Utils::getPercentage($e->downloaded, $e->downloadSize),
                    Utils::formatBytes($e->downloaded),
                    Utils::formatBytes($e->downloadSize));
            });
        }

        $this->tryDownload($req);

        $this->bench->end();

        Utils::write(sprintf("Elapsed time: %s, Memory: %s         ",
            $this->bench->getTime(),
            $this->bench->getMemoryUsage()
        ));

        return true;
    }

    private function tryDownload($req) {
        try {
            $this->client->send($req);
        } catch (\Exception $e) {
            if (!$this->retryDownload) {
                throw $e;
            }
            Utils::write(sprintf("Retry download after connection fail!"));
            $this->tryDownload($req);
        }
    }
}
