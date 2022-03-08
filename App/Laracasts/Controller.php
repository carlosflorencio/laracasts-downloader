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
     * @param array $filters
     * @return array
     */
    public function getSeries($cachedData, $filters)
    {
        $seriesCollection = new SeriesCollection(empty($filters) ? $cachedData : []);

        $topics = Parser::getTopicsData($this->client->getTopicsHtml());

        foreach ($topics as $topic) {

            // TODO: It's not gonna work fine
            // for example blade topic has 2 series (11e+9e=20)
            // cache file only includes 1 series in with blade topic and the other in alpine-js category
            if ($this->isTopicUpdated($seriesCollection, $topic))
                continue;

            Utils::box($topic['slug']);

            $topicHtml = $this->client->getHtml($topic['path']);

            $series = Parser::getSeriesData($topicHtml);

            foreach ($series as $serie) {
                if (! empty($filters) and ! array_key_exists($serie['slug'], $filters))
                    continue;

                if ($this->isSerieUpdated($seriesCollection, $serie))
                    continue;

                Utils::writeln("Getting serie: {$serie['slug']} ...");

                $serie['topic'] = $topic['slug'];

                $episodes = (isset($filters[$serie['slug']]) and ! empty($filters[$serie['slug']]))
                    ? $filters[$serie['slug']]
                    : range(1, $serie['episode_count']);

                foreach ($episodes as $episode) {
                    $episodeHtml = $this->client->getHtml($serie['path'] . '/episodes/' . $episode);

                    $serie['episodes'][] = Parser::getEpisodesData($episodeHtml);
                }

                $seriesCollection->add($serie);
            }
        }

        Utils::box('Larabits');

        $larabitsHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/bits');

        $bits = Parser::extractLarabitsSeries($larabitsHtml);

        foreach ($bits as $bit) {
            if (! empty($filters) and ! array_key_exists($bit, $filters))
                continue;

            Utils::writeln("Getting serie: $bit ...");

            $seriHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/series/' . $bit);

            $serie = Parser::getLarabitsData($seriHtml);
            $serie['topic'] = 'larabits';

            foreach (range(1, $serie['episode_count']) as $episode) {
                $episodeHtml = $this->client->getHtml($serie['path'] . '/episodes/' . $episode);

                $serie['episodes'][] = Parser::getEpisodesData($episodeHtml);
            }

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
