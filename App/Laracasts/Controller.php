<?php


namespace App\Laracasts;


use App\Html\Parser;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class Controller
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;
    /**
     * @var array
     */
    private $algoliaResults;

    /**
     * Controller constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->cookie = new CookieJar();
    }

    /**
     * Adds algolia results for use while merging
     *
     * @param array $algoliaLessons
     */
    public function addAlgoliaResults(array $algoliaLessons)
    {
        $this->algoliaResults = $algoliaLessons;
    }

    /**
     *  Gets all series with scraping and merges with algolia result
     *
     * @return array
     * @throws \Exception
     */
    public function getAllSeries()
    {
        if (empty($this->algoliaResults)) throw new \Exception('algoliaResults is empty, use addAlgoliaResults() to add a result');

        $mergedResult = $this->algoliaResults;

        foreach($this->algoliaResults['series'] as $slug => $algoliaCourse) {
            $episodesCount = Parser::getEpisodesCount($this->getCourseHTML($slug));

            foreach (range(1, $episodesCount) as $episode) {
                $key = $episode - 1; // Since we override we can't just append to the array
                $mergedResult['series'][$slug][$key] = $episode;
            }
        }

        return $mergedResult;
    }

    /**
     * Returns series page html
     *
     * @return string
     */
    private function getSeriesHtml()
    {
        return $this->client
            ->get(LARACASTS_BASE_URL . '/' . LARACASTS_SERIES_PATH, ['cookies' => $this->cookie, 'verify' => false])
            ->getBody()
            ->getContents();
    }

    /**
     * Returns specific course page html
     *
     * @param string $slug
     * @return string
     */
    private function getCourseHTML($slug)
    {
        return $this->client
            ->get(LARACASTS_BASE_URL . '/' . LARACASTS_SERIES_PATH . '/' . $slug, ['cookies' => $this->cookie, 'verify' => false])
            ->getBody()
            ->getContents();
    }
}
