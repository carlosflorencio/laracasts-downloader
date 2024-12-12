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
use Ubench;

/**
 * Class Resolver
 */
class Resolver
{
    /**
     * Guzzle cookie
     */
    private readonly CookieJar $cookies;

    /**
     * Receives dependencies
     */
    public function __construct(
        /**
         * Guzzle client
         */
        private readonly Client $client,
        /**
         * Ubench lib
         */
        private readonly Ubench $bench,
    ) {
        $this->cookies = new CookieJar;
    }

    /**
     * Tries to authenticate user.
     *
     * @param  string  $email
     * @param  string  $password
     */
    public function login($email, $password): array
    {
        $token = $this->getCsrfToken();

        $response = $this->client->post(LARACASTS_POST_LOGIN_PATH, [
            'cookies' => $this->cookies,
            'headers' => [
                'X-XSRF-TOKEN' => $token,
                'content-type' => 'application/json',
                'x-requested-with' => 'XMLHttpRequest',
                'referer' => LARACASTS_BASE_URL,
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
     */
    public function getCsrfToken(): string
    {
        $this->client->get(LARACASTS_BASE_URL, [
            'cookies' => $this->cookies,
            'headers' => [
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'referer' => LARACASTS_BASE_URL,
            ],
            'verify' => false,
        ]);

        $token = current(
            array_filter($this->cookies->toArray(), fn ($cookie): bool => $cookie['Name'] === 'XSRF-TOKEN')
        );

        return urldecode((string) $token['Value']);
    }

    /**
     * Download the episode of the serie.
     *
     * @return bool
     */
    public function downloadEpisode(string $serieSlug, array $episode)
    {
        try {
            $number = sprintf('%02d', $episode['number']);
            $name = $episode['title'];
            $filepath = $this->getFilename($serieSlug, $number, $name);

            Utils::writeln(
                sprintf(
                    'Download started: %s . . . . Saving on '.SERIES_FOLDER.'/'.$serieSlug,
                    $number.' - '.$name
                )
            );

            $source = $_ENV['DOWNLOAD_SOURCE'];

            if (! $source || $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($serieSlug, $episode['number']);

                return $this->downloadVideo($downloadLink, $filepath);
            } else {
                $vimeoDownloader = new VimeoDownloader;

                return $vimeoDownloader->download($episode['vimeo_id'], $filepath);
            }
        } catch (RequestException $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * @param  string  $episodeName
     */
    private function getFilename(string $serieSlug, string $number, $episodeName): string
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
     */
    public function getTopicsHtml(): string
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
     */
    public function getHtml($url): string
    {
        return $this->client
            ->get($url, ['cookies' => $this->cookies, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    /**
     * Get Laracasts download link for given episode
     *
     * @param  int  $episodeNumber
     * @return string
     */
    private function getLaracastsLink(string $serieSlug, $episodeNumber)
    {
        $episodeHtml = $this->getHtml("series/$serieSlug/episodes/$episodeNumber");

        return Parser::getEpisodeDownloadLink($episodeHtml);
    }

    /**
     * Helper to get the Location header.
     *
     *
     * @return string
     */
    private function getRedirectUrl($url): array
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
     */
    private function downloadVideo($downloadUrl, string $saveTo): bool
    {
        $this->bench->start();

        $link = $this->prepareDownloadLink($downloadUrl);

        try {
            $downloadedBytes = file_exists($saveTo) ? filesize($saveTo) : 0;
            $this->client->request('GET', $link['url'], [
                'query' => $link['query'],
                'sink' => fopen($saveTo, 'a'),
                'progress' => fn ($downloadTotal, $downloadedBytes) => Utils::showProgressBar($downloadedBytes, $downloadTotal),
            ]);
        } catch (Exception $e) {
            echo $e->getMessage().PHP_EOL;

            return false;
        }

        $this->bench->end();

        Utils::write(
            sprintf(
                'Elapsed time: %s, Memory: %s       ',
                $this->bench->getTime(),
                $this->bench->getMemoryUsage()
            )
        );

        return true;
    }

    /**
     * @param  string  $url
     */
    private function prepareDownloadLink($url): array
    {
        $url = $this->getRedirectUrl($url);
        $url = $this->getRedirectUrl($url);
        $parts = parse_url($url);

        return [
            'query' => $parts['query'],
            'url' => $parts['scheme'].'://'.$parts['host'].$parts['path'],
        ];
    }
}
