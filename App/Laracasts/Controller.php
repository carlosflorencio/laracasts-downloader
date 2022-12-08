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
     * @param bool $cacheOnly
     * @return array
     */
    public function getSeries($cachedData, $cacheOnly = false)
    {
        $seriesCollection = new SeriesCollection($cachedData);

        if ($cacheOnly) {
            return $seriesCollection->get();
        }

        $topics = Parser::getTopicsData($this->client->getTopicsHtml());

        foreach ($topics as $topic) {

            // TODO: It's not gonna work fine because each series may have multiple topics
            if ($this->isTopicUpdated($seriesCollection, $topic))
                continue;

            Utils::box($topic['slug']);

            $topicHtml = $this->client->getHtml($topic['path']);

            $series = Parser::getSeriesDataFromTopic($topicHtml);

            foreach ($series as $serie) {
                if ($this->isSerieUpdated($seriesCollection, $serie))
                    continue;

                Utils::writeln("Getting serie: {$serie['slug']} ...");

                $serie['topic'] = $topic['slug'];

                $episodeHtml = $this->client->getHtml($serie['path'] . '/episodes/1');

                $serie['episodes'] = Parser::getEpisodesData($episodeHtml);

                $seriesCollection->add($serie);
            }
        }

        Utils::box('Larabits');

        $larabitsHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/bits');

        $bits = Parser::extractLarabitsSeries($larabitsHtml);

        foreach ($bits as $bit) {
            Utils::writeln("Getting serie: $bit ...");

            $seriHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/series/' . $bit);

            $serie = Parser::getSerieData($seriHtml);

            $serie['topic'] = 'larabits';

            $episodeHtml = $this->client->getHtml($serie['path'] . '/episodes/1');

            $serie['episodes'] = Parser::getEpisodesData($episodeHtml);

            $seriesCollection->add($serie);
        }

        return $seriesCollection->get();
    }

    public function getFilteredSeries($filters)
    {
        $seriesCollection = new SeriesCollection([]);

        foreach($filters as $serieSlug => $filteredEpisodes) {
            $seriesHtml = $this->client->getHtml("series/$serieSlug");

            $serie = Parser::getSerieData($seriesHtml);

            $episodeHtml = $this->client->getHtml($serie['path'] . '/episodes/1');

            $serie['episodes'] = Parser::getEpisodesData($episodeHtml, $filteredEpisodes);

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
