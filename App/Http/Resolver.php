<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Html\Parser;
use App\Utils\Utils;
use App\Vimeo\VimeoDownloader;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Query;
use Ubench;

/**
 * Class Resolver
 *
 * @package App\Http
 */
class Resolver
{
    /**
     * Guzzle client
     *
     * @var Client
     */
    private $client;

    /**
     * Guzzle cookie
     *
     * @var CookieJar
     */
    private $cookies;

    /**
     * Ubench lib
     *
     * @var Ubench
     */
    private $bench;

    /**
     * Retry download on connection fail
     *
     * @var int
     */
    private $retryDownload = false;

    /**
     * Receives dependencies
     *
     * @param  Client  $client
     * @param  Ubench  $bench
     * @param  bool  $retryDownload
     */
    public function __construct(Client $client, Ubench $bench, $retryDownload = false)
    {
        $this->client = $client;
        $this->cookies = new CookieJar();
        $this->bench = $bench;
        $this->retryDownload = $retryDownload;
    }

    /**
     * Tries to authenticate user.
     *
     * @param  string  $email
     * @param  string  $password
     *
     * @return array
     */
    public function login($email, $password)
    {
        $token = $this->getCsrfToken();

        $response = $this->client->post(LARACASTS_POST_LOGIN_PATH, [
            'cookies' => $this->cookies,
            'headers' => [
                "X-XSRF-TOKEN" => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'x-requested-with' => 'XMLHttpRequest',
                'referer' => LARACASTS_BASE_URL,
                'User-Agent' => REQUEST_USER_AGENT,
            ],
            'body' => json_encode([
                'email' => $email,
                'password' => $password,
                'remember' => 1,
            ]),
            'verify' => false,
        ]);

        $html = $response->getBody()->getContents();

        return Parser::getUserData($html);
    }

    /**
     * Returns CSRF token
     *
     * @return string
     */
    public function getCsrfToken()
    {
        $this->client->get(LARACASTS_BASE_URL, [
            'cookies' => $this->cookies,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'referer' => LARACASTS_BASE_URL,
                'x-requested-with' => 'XMLHttpRequest',
                'User-Agent' => REQUEST_USER_AGENT,
            ],
            'verify' => false,
        ]);

        $token = current(
            array_filter($this->cookies->toArray(), function($cookie) {
                return $cookie['Name'] === 'XSRF-TOKEN';
            })
        );

        return urldecode($token['Value']);
    }

    /**
     * Download the episode of the serie.
     *
     * @param  string  $serieSlug
     * @param  array  $episode
     *
     * @return bool
     */
    public function downloadEpisode($serieSlug, $episode)
    {
        try {
            $number = sprintf("%02d", $episode['number']);
            $name = $episode['title'];
            $filepath = $this->getFilename($serieSlug, $number, $name);

            Utils::writeln(
                sprintf(
                    "Download started: %s . . . . Saving on ".SERIES_FOLDER.'/'.$serieSlug,
                    $number.' - '.$name
                )
            );

            $source = getenv('DOWNLOAD_SOURCE');

            if (! $source or $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($serieSlug, $episode['number']);

                return $this->downloadVideo($downloadLink, $filepath);
            } else {
                $vimeoDownloader = new VimeoDownloader();

                return $vimeoDownloader->download($episode['vimeo_id'], $filepath);
            }
        } catch (RequestException $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * @param  string  $serieSlug
     * @param  string  $number
     * @param  string  $episodeName
     *
     * @return string
     */
    private function getFilename($serieSlug, $number, $episodeName)
    {
        return BASE_FOLDER
            .DIRECTORY_SEPARATOR
            .SERIES_FOLDER
            .DIRECTORY_SEPARATOR
            .$serieSlug
            .DIRECTORY_SEPARATOR
            .$number
            .'-'
            .Utils::parseEpisodeName($episodeName)
            .'.mp4';
    }

    /**
     * Returns topics page html
     *
     * @return string
     */
    public function getTopicsHtml()
    {
        return $this->client
            ->get(LARACASTS_BASE_URL.'/'.LARACASTS_TOPICS_PATH, ['cookies' => $this->cookies, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    /**
     * Returns html content of specific url
     *
     * @param  string  $url
     *
     * @return string
     */
    public function getHtml($url)
    {
        return $this->client
            ->get($url, ['cookies' => $this->cookies, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    /**
     * Get Laracasts download link for given episode
     *
     * @param  string  $serieSlug
     * @param  int  $episodeNumber
     *
     * @return string
     */
    private function getLaracastsLink($serieSlug, $episodeNumber)
    {
        $episodeHtml = $this->getHtml("series/$serieSlug/episodes/$episodeNumber");

        return Parser::getEpisodeDownloadLink($episodeHtml);
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
            'cookies' => $this->cookies,
            'allow_redirects' => false,
            'verify' => false,
        ]);

        return $response->getHeader('Location');
    }

    /**
     * Helper to download the video.
     *
     * @param $downloadUrl
     * @param $saveTo
     *
     * @return bool
     */
    private function downloadVideo($downloadUrl, $saveTo)
    {
        $this->bench->start();

        $link = $this->prepareDownloadLink($downloadUrl);

        try {
            $downloadedBytes = file_exists($saveTo) ? filesize($saveTo) : 0;
            $req = $this->client->createRequest('GET', $link['url'], [
                'query' => Query::fromString($link['query'], false),
                'save_to' => fopen($saveTo, 'a'),
            ]);

            Utils::showProgressBar($req, $downloadedBytes);

            $this->client->send($req);

        } catch (Exception $e) {
            echo $e->getMessage().PHP_EOL;

            return false;
        }

        $this->bench->end();

        Utils::write(
            sprintf(
                "Elapsed time: %s, Memory: %s       ",
                $this->bench->getTime(),
                $this->bench->getMemoryUsage()
            )
        );

        return true;
    }

    /**
     * @param string $url
    */
    private function prepareDownloadLink($url)
    {
        $url = $this->getRedirectUrl($url);
        $url = $this->getRedirectUrl($url);
        $parts = parse_url($url);

        return [
            'query' => $parts['query'],
            'url' => $parts['scheme'].'://'.$parts['host'].$parts['path']
        ];
    }
}
