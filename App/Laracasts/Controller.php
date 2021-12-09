<?php


namespace App\Laracasts;


use App\Html\Parser;
use App\Http\Resolver;
use App\Utils\SeriesCollection;
use App\Utils\Utils;

class Controller
{
    /**
     * @var \App\Http\Resolver
     */
    private $client;

    /**
     * Controller constructor.
     *
     * @param Resolver $client
     */
    public function __construct(Resolver $client)
    {
        $this->client = $client;
    }

    /**
     *  Gets all series using scraping
     *
     * @param array $cachedData
     * @return array
     */
    public function getSeries($cachedData)
    {
        $seriesCollection = new SeriesCollection($cachedData);

        $topics = Parser::getTopicsData($this->client->getTopicsHtml());

        foreach ($topics as $topic) {

            if ($this->isTopicUpdated($seriesCollection, $topic))
                continue;

            Utils::box($topic['slug']);

            $topicHtml = $this->client->getHtml($topic['path']);

            $series = Parser::getSeriesData($topicHtml);

            foreach ($series as $serie) {
                if ($this->isSerieUpdated($seriesCollection, $serie))
                    continue;

                Utils::writeln("Getting serie: {$serie['slug']} ...");

                $seriHtml = $this->client->getHtml($serie['path']);

                $serie['topic'] = $topic['slug'];

                $serie['episodes'] = Parser::getEpisodesData($seriHtml);

                $seriesCollection->add($serie);
            }

        }

        Utils::box('Larabits');

        $larabitsHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/bits');

        $bits = Parser::extractLarabitsSeries($larabitsHtml);

        foreach ($bits as $bit) {
            Utils::writeln("Getting serie: $bit ...");

            $seriHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/series/' . $bit);

            $serie = Parser::getLarabitsData($seriHtml);

            $seriesCollection->add($serie);
        }

        return $seriesCollection->get();
    }


    /**
     *  Determine is specific topic has been changed compared to cached data
     *
     * @param SeriesCollection $series
     * @param array $topic
     * @return bool
     * */
    public function isTopicUpdated($series, $topic)
    {
        $series = $series->where('topic', $topic['slug']);

        return
            $series->exists()
            and
            $topic['series_count'] == $series->count()
            and
            $topic['episode_count'] == $series->sum('episode_count', true);
    }

    /**
     * Determine is specific series has been changed compared to cached data
     *
     * @param SeriesCollection $series
     * @param array $serie
     * @return bool
     */
    private function isSerieUpdated($series, $serie)
    {
        $target = $series->where('slug', $serie['slug'])->first();

        return ! is_null($target) and (count($target['episodes']) == $serie['episode_count']);
    }
}
