<?php
/**
 * Http functions
 */

namespace App\Http;

use App\Html\Parser;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Event\ProgressEvent;
use GuzzleHttp\Exception\RequestException;
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
     * @param Client $client
     * @param Ubench $bench
     * @param bool $retryDownload
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
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login($email, $password)
    {
        $token = $this->getCsrfToken();

        $response = $this->client->post(LARACASTS_POST_LOGIN_PATH, [
            'cookies' => $this->cookies,
            'headers' => [
                "X-XSRF-TOKEN" => $token,
                'content-type' => 'application/json',
                'x-requested-with' => 'XMLHttpRequest',
                'referer' => 'https://laracasts.com/',
            ],
            'body' => json_encode([
                'email' => $email,
                'password' => $password,
                'remember' => 1
            ]),
            'verify' => false
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
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'referer' => 'https://laracasts.com/',
            ],
            'verify' => false
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
     * @param string $serieSlug
     * @param array $episode
     * @return bool
     */
    public function downloadEpisode($serieSlug, $episode)
    {
        try {
            $name = $episode['title'];

            $number = sprintf("%02d", $episode['number']);

            $saveTo = BASE_FOLDER
                . DIRECTORY_SEPARATOR
                . SERIES_FOLDER
                . DIRECTORY_SEPARATOR
                . $serieSlug
                . DIRECTORY_SEPARATOR
                . $number . '-' . Utils::parseEpisodeName($name) . '.mp4';

            Utils::writeln(
                sprintf(
                    "Download started: %s . . . . Saving on " . SERIES_FOLDER . '/' . $serieSlug,
                    $number . ' - ' . $name
                ));

            $links = $this->getVimeoLinks($episode['vimeo_id']);

            $downloadLink = $links[VIDEO_QUALITY] ?? current($links);

            return $this->downloadVideo($downloadLink, $saveTo);
        } catch (RequestException $e) {
            Utils::write(sprintf($e->getMessage()));

            return false;
        }
    }


    /**
     * Returns topics page html
     *
     * @return string
     */
    public function getTopicsHtml()
    {
        return $this->client
            ->get(LARACASTS_BASE_URL . '/' . LARACASTS_TOPICS_PATH, ['cookies' => $this->cookies, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    /**
     * Returns html content of specific url
     *
     * @param string $url
     * @return string
     */
    public function getHtml($url)
    {
        return $this->client
            ->get($url, ['cookies' => $this->cookies, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    private function getVimeoLinks($viemoId)
    {
        $content = $this->client->get('https://player.vimeo.com/video/' . $viemoId, [
            'cookies' => $this->cookies,
            'verify' => false,
            'headers' => [
                'Referer' => 'https://laracasts.com/'
            ]
        ])->getBody()->getContents();
        preg_match('/"progressive":\[(.+?)\]/', $content, $matches);

        $result = json_decode('{' . $matches[0] . '}', true);

        $output = [];

        foreach ($result['progressive'] as $progressive) {
            $output[$progressive['quality']] = $progressive['url']; //. '?download=1'
        }

        return $output;
    }

    /**
     * Helper to download the video.
     *
     * @param $downloadUrl
     * @param $saveTo
     * @return bool
     */
    private function downloadVideo($downloadUrl, $saveTo)
    {
        $this->bench->start();

        $finalUrl = $downloadUrl;

        // We need this to send proper range otherwise will face "416 Range Not Satisfiable" error
        // @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/416
        $contentRange = $this->client->get($finalUrl, [
            'headers' => ['Range' => 'bytes=0-0']
        ])->getHeader('Content-Range');

        $downloadSize = substr($contentRange, strpos($contentRange, '/') + 1);

        $retries = 0;

        while (true) {
            try {
                $downloadedBytes = file_exists($saveTo) ? filesize($saveTo) : 0;
                $req = $this->client->createRequest('GET', $finalUrl, [
                    'save_to' => fopen($saveTo, 'a'),
                    'verify' => false,
                    'headers' => [
                        'Range' => 'bytes=' . $downloadedBytes . '-' . $downloadSize,
                    ]
                ]);

                if (php_sapi_name() == "cli") { //on cli show progress
                    $req->getEmitter()->on('progress', function(ProgressEvent $e) use ($downloadedBytes) {
                        printf("> Total: %d%% Downloaded: %s of %s     \r",
                            Utils::getPercentage($e->downloaded + $downloadedBytes, $e->downloadSize),
                            Utils::formatBytes($e->downloaded + $downloadedBytes),
                            Utils::formatBytes($e->downloadSize));
                    });
                }

                $this->client->send($req);

                break;
            } catch (\Exception $e) {
                ++$retries;
                Utils::writeln(sprintf("Retry download after connection fail!     "));
                continue;
            }
        }

        $this->bench->end();

        Utils::write(sprintf("Elapsed time: %s, Memory: %s         ",
            $this->bench->getTime(),
            $this->bench->getMemoryUsage()
        ));

        return true;
    }
}
