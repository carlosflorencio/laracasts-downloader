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

        $seriesHtml = $this->getSeriesHtml();

        $mergedResult = $this->algoliaResults;

        $series = Parser::getSeriesArray($seriesHtml);

        foreach ($series as $serie) {
            foreach (range(1, $serie['episode_count']) as $episode) {
                $key = $episode - 1; // Since we override we can't just append to the array
                $mergedResult['series'][$serie['slug']][$key] = $episode;
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
}