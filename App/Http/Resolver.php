<?php

/**
 * Http functions
 */

namespace App\Http;

use App\Cloudflare\CloudflareDownloader;
use App\Html\Parser;
use App\Mux\ChapterMetadata;
use App\Mux\ExternalDownloader;
use App\Mux\MuxDownloader;
use App\Utils\Metadata;
use App\Utils\Utils;
use App\Vimeo\VimeoDownloader;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Throwable;
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
        private readonly Client $client,
        private readonly Ubench $bench,
    ) {
        $this->cookies = new CookieJar;
    }

    /**
     * Tries to authenticate user.
     */
    public function login(string $email, string $password): array
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
     */
    public function downloadEpisode(string $serieSlug, array $episode): bool
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

            $source = $_ENV['DOWNLOAD_SOURCE'] ?? null;

            if (! $source || $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($serieSlug, $episode['number']);

                $downloaded = $this->downloadVideo($downloadLink, $filepath);

                if ($downloaded) {
                    $this->applyMetadata($filepath, $serieSlug, $episode);
                    $this->applyPublishDate($filepath, $episode['published'] ?? null);
                }

                return $downloaded;
            }

            if ($source === 'vimeo') {
                if (empty($episode['vimeo_id'])) {
                    Utils::write('Laracasts no longer streams from Vimeo. Set DOWNLOAD_SOURCE=mux in your .env');

                    return false;
                }

                $vimeoDownloader = new VimeoDownloader;

                $downloaded = $vimeoDownloader->download($episode['vimeo_id'], $filepath);

                if ($downloaded) {
                    $this->applyMetadata($filepath, $serieSlug, $episode);
                    $this->applyPublishDate($filepath, $episode['published'] ?? null);
                }

                return $downloaded;
            }

            if ($source === 'mux' || $source === 'external') {
                // Playback data is fetched fresh from the episode page at
                // download time: Mux tokens are short-lived (~2h), and a lesson
                // may have been migrated to the Cloudflare CDN since the
                // catalogue scrape, so detect the host per-episode.
                $episodeHtml = $this->getHtml("series/$serieSlug/episodes/{$episode['number']}");

                $chapters = ChapterMetadata::enabled() ? Parser::getEpisodeChapters($episodeHtml) : [];

                $cloudflare = Parser::getEpisodeCloudflarePlayback($episodeHtml);

                if ($cloudflare !== null) {
                    $cookieHeader = $this->cookieHeaderFor($cloudflare['src']);

                    $downloaded = $source === 'external'
                        ? (new ExternalDownloader)->downloadFromUrl($cloudflare, $cookieHeader, $filepath, $chapters)
                        : (new CloudflareDownloader)->download($cloudflare, $cookieHeader, $filepath, $chapters);
                } else {
                    [$playbackId, $token] = Parser::getEpisodeMuxPlayback($episodeHtml);

                    $downloader = $source === 'external' ? new ExternalDownloader : new MuxDownloader;

                    $downloaded = $downloader->download($playbackId, $token, $filepath, $chapters);
                }

                if ($downloaded) {
                    $this->applyMetadata($filepath, $serieSlug, $episode, Parser::getSeriesTitle($episodeHtml), Parser::getEpisodeInstructor($episodeHtml));
                    $this->applyPublishDate($filepath, Parser::getEpisodePublishDate($episodeHtml) ?? $episode['published'] ?? null);
                }

                return $downloaded;
            }

            throw new Exception("Unsupported DOWNLOAD_SOURCE: $source");
        } catch (Throwable $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * Fetch and save only the chapter sidecar of an episode (no video),
     * regardless of whether the episode itself is downloaded.
     */
    public function downloadEpisodeChapters(string $serieSlug, array $episode): bool
    {
        try {
            $number = sprintf('%02d', $episode['number']);
            $filepath = $this->getFilename($serieSlug, $number, $episode['title']);
            $sidecar = ChapterMetadata::sidecarPath($filepath);

            if (file_exists($sidecar) || file_exists(ChapterMetadata::mergedSidecarPath($filepath))) {
                Utils::writeln('Chapters already present: '.basename($sidecar));

                return true;
            }

            Utils::writeln(
                sprintf(
                    'Fetching chapters: %s . . . . Saving on '.SERIES_FOLDER.'/'.$serieSlug,
                    $number.' - '.$episode['title']
                )
            );

            $episodeHtml = $this->getHtml("series/$serieSlug/episodes/{$episode['number']}");

            $chapters = Parser::getEpisodeChapters($episodeHtml);

            if ($chapters === []) {
                Utils::writeln('No chapter data for this episode.');

                return true;
            }

            ChapterMetadata::saveNextTo($filepath, $chapters);

            Utils::writeln(sprintf('Saved %d chapters', count($chapters)));

            return true;
        } catch (Throwable $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * Fetch and save only the subtitles of an episode (no video),
     * regardless of whether the episode itself is downloaded.
     */
    public function downloadEpisodeSubtitles(string $serieSlug, array $episode): bool
    {
        try {
            $number = sprintf('%02d', $episode['number']);
            $filepath = $this->getFilename($serieSlug, $number, $episode['title']);

            // any existing .vtt next to the episode counts as present
            $existing = glob(preg_replace('/\.[^.]+$/', '', $filepath).'.*.vtt');

            if ($existing !== false && $existing !== []) {
                Utils::writeln('Subtitles already present: '.basename($existing[0]));

                return true;
            }

            Utils::writeln(
                sprintf(
                    'Fetching subtitles: %s . . . . Saving on '.SERIES_FOLDER.'/'.$serieSlug,
                    $number.' - '.$episode['title']
                )
            );

            $episodeHtml = $this->getHtml("series/$serieSlug/episodes/{$episode['number']}");

            $cloudflare = Parser::getEpisodeCloudflarePlayback($episodeHtml);

            if ($cloudflare !== null) {
                return (new CloudflareDownloader)->downloadSubtitlesOnly(
                    $cloudflare,
                    $this->cookieHeaderFor($cloudflare['src']),
                    $filepath
                );
            }

            [$playbackId, $token] = Parser::getEpisodeMuxPlayback($episodeHtml);

            return (new MuxDownloader)->downloadSubtitlesOnly($playbackId, $token, $filepath);
        } catch (Throwable $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * Stamp a freshly-downloaded episode with its original publish date
     * (day precision, normalised to 12:00). Best-effort: quietly does
     * nothing when the date is unknown.
     */
    private function applyPublishDate(string $filepath, ?string $published): void
    {
        $timestamp = $published === null || $published === '' ? false : strtotime($published.' 12:00:00');

        if ($timestamp !== false && file_exists($filepath)) {
            touch($filepath, $timestamp);
        }
    }

    /**
     * Tag the freshly-downloaded mp4 with the lesson number, series title,
     * lesson title and instructor (artist) when WRITE_METADATA is enabled.
     * Runs before applyPublishDate since the remux resets the file mtime.
     * The instructor defaults to the catalogue value carried on $episode.
     */
    private function applyMetadata(string $filepath, string $serieSlug, array $episode, ?string $seriesTitle = null, ?string $instructor = null): void
    {
        if (! Metadata::enabled()) {
            return;
        }

        Metadata::write($filepath, [
            'title' => $episode['title'] ?? '',
            'artist' => (string) ($instructor ?? $episode['instructor'] ?? ''),
            'album' => $seriesTitle ?: Utils::humanizeSlug($serieSlug),
            'track' => (string) $episode['number'],
        ]);
    }

    /**
     * Set an already-downloaded episode's last-modified time to its
     * original publish date (day precision, normalised to 12:00) —
     * no downloads.
     */
    public function setEpisodeTimestamp(string $serieSlug, array $episode): bool
    {
        $number = sprintf('%02d', $episode['number']);
        $filepath = $this->getFilename($serieSlug, $number, $episode['title']);
        $filename = basename($filepath);

        if (! file_exists($filepath)) {
            Utils::writeln("Not downloaded, skipping: $filename");

            return true;
        }

        $timestamp = empty($episode['published']) ? false : strtotime($episode['published'].' 12:00:00');

        if ($timestamp === false) {
            Utils::writeln("No publish date for: $filename");

            return true;
        }

        if (date('Y-m-d', filemtime($filepath)) === date('Y-m-d', $timestamp)) {
            Utils::writeln("Timestamp already correct: $filename");

            return true;
        }

        Utils::writeln(sprintf('Setting timestamp %s on %s', date('Y-m-d', $timestamp), $filename));

        return touch($filepath, $timestamp);
    }

    /**
     * Fetch a series' display title from its landing page (for metadata
     * tagging), or null when it cannot be read.
     */
    public function fetchSeriesTitle(string $serieSlug): ?string
    {
        try {
            return Parser::getSeriesTitle($this->getHtml("series/$serieSlug"));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Per-episode instructor map (number => full name) for a series, fetched
     * once from its episode list page; empty array when unavailable. Lets the
     * metadata backfill tag the artist when the cache predates instructor
     * capture (older cache.json entries carry no instructor).
     *
     * @return array<int, string>
     */
    public function fetchSeriesInstructors(string $serieSlug): array
    {
        try {
            return Parser::getEpisodeInstructors($this->getHtml("series/$serieSlug/episodes/1"));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Backfill metadata (lesson number, series title, lesson title and
     * instructor) into an already-downloaded episode mp4 — no video download.
     * The episode is located by its NN- number prefix, so the exact title is
     * not required.
     */
    public function updateEpisodeMetadata(string $serieSlug, int $number, ?string $lessonTitle, ?string $seriesTitle, ?string $instructor = null): bool
    {
        $dir = BASE_FOLDER.DIRECTORY_SEPARATOR.SERIES_FOLDER.DIRECTORY_SEPARATOR.$serieSlug;

        $matches = glob($dir.DIRECTORY_SEPARATOR.sprintf('%02d-', $number).'*.mp4');

        if ($matches === false || $matches === []) {
            Utils::writeln(sprintf('Not downloaded, skipping: %s/%02d', $serieSlug, $number));

            return true;
        }

        $filepath = $matches[0];

        // fall back to the on-disk title when the online title is unknown
        if ($lessonTitle === null || $lessonTitle === '') {
            $lessonTitle = preg_replace('/^\d+-/', '', pathinfo($filepath, PATHINFO_FILENAME));
        }

        Utils::writeln('Writing metadata: '.basename($filepath));

        return Metadata::write($filepath, [
            'title' => (string) $lessonTitle,
            'artist' => (string) $instructor,
            'album' => $seriesTitle ?: Utils::humanizeSlug($serieSlug),
            'track' => (string) $number,
        ]);
    }

    private function getFilename(string $serieSlug, string $number, string $episodeName): string
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
     * Returns html content of specific url
     */
    public function getHtml(string $url): string
    {
        return $this->client
            ->get($url, ['cookies' => $this->cookies, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    /**
     * Build the Cookie request header that authorizes the Cloudflare media
     * CDN, selected from the shared login jar by domain/path match (the CDN
     * carries no token in the url and rejects unauthenticated requests).
     */
    public function cookieHeaderFor(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '/';

        $pairs = [];

        /** @var SetCookie $cookie */
        foreach ($this->cookies as $cookie) {
            if ($cookie->isExpired()) {
                continue;
            }

            if ($cookie->matchesDomain($host) && $cookie->matchesPath($path)) {
                $pairs[] = $cookie->getName().'='.$cookie->getValue();
            }
        }

        return implode('; ', $pairs);
    }

    /**
     * Get Laracasts download link for given episode
     */
    private function getLaracastsLink(string $serieSlug, int $episodeNumber): string
    {
        $episodeHtml = $this->getHtml("series/$serieSlug/episodes/$episodeNumber");

        return Parser::getEpisodeDownloadLink($episodeHtml);
    }

    /**
     * Helper to get the Location header.
     */
    private function getRedirectUrl(string $url): string
    {
        $response = $this->client->get($url, [
            'cookies' => $this->cookies,
            'allow_redirects' => false,
            'verify' => false,
        ]);

        return $response->getHeader('Location')[0] ?? '';
    }

    /**
     * Helper to download the video.
     */
    private function downloadVideo(string $downloadUrl, string $saveTo): bool
    {
        $this->bench->start();

        $link = $this->prepareDownloadLink($downloadUrl);

        try {
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

    private function prepareDownloadLink(string $url): array
    {
        $parts = parse_url($this->getRedirectUrl($url));

        return [
            'query' => $parts['query'],
            'url' => $parts['scheme'].'://'.$parts['host'].$parts['path'],
        ];
    }

    /**
     * This API can return a JSON response if following headers are provided,
     * 'X-Inertia' => 'true',
     * 'x-requested-with' => 'XMLHttpRequest',
     * 'x-inertia-version' => '68097aab2991864455c8c421d304aa3a'
     * However, obtaining the correct X-Inertia-Version requires extracting it dynamically (from data-page attribute),
     * which adds unnecessary complexity for our use case.
     * Therefore, it's simpler and more reliable to parse the HTML response directly instead.
     *
     * @return array{data: array, has_more: bool}
     */
    public function getSeries(int $page = 1): array
    {
        $url = LARACASTS_BASE_URL."/series?page=$page";

        $response = $this->client->get($url, [
            'cookies' => $this->cookies,
            'headers' => [
                'Accept' => 'text/html, application/xhtml+xml',
                'Content-Type' => 'application/json',
                'referer' => LARACASTS_BASE_URL,
            ],
            'verify' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception('series api response status code is '.$response->getStatusCode());
        }

        $html = $response->getBody()->getContents();

        $data = Parser::getData($html);

        if (! isset($data['props']['series']['data'])) {
            throw new Exception('unexpected response structure for series.');
        }

        return [
            'data' => array_map(fn ($serie): array => Parser::mapSerieData($serie), $data['props']['series']['data']),
            'has_more' => $data['props']['series']['meta']['last_page'] > $page,
        ];
    }
}
