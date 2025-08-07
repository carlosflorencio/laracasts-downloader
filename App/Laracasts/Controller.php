<?php

namespace App\Laracasts;

use App\Html\Parser;
use App\Http\Resolver;
use App\Utils\SeriesCollection;
use App\Utils\Utils;

class Controller
{
    /**
     * Controller constructor.
     */
    public function __construct(private readonly Resolver $client) {}

    /**
     *  Gets all series using scraping
     */
    public function getSeries(array $cachedData, bool $cacheOnly = false): array
    {
        $seriesCollection = new SeriesCollection($cachedData);

        if ($cacheOnly) {
            return $seriesCollection->get();
        }

        $series = $this->client->getSeries();

        foreach ($series as $serie) {
            if ($this->isSerieUpdated($seriesCollection, $serie)) {
                continue;
            }

            Utils::writeln("Getting serie: {$serie['slug']} ...");

            $episodeHtml = $this->client->getHtml($serie['path'].'/episodes/1');

            $serie['episodes'] = Parser::getEpisodesData($episodeHtml);

            $seriesCollection->add($serie);
        }

        Utils::box('Larabits');

        $larabitsHtml = $this->client->getHtml(LARACASTS_BASE_URL.'/bits');

        $bits = Parser::extractLarabitsSeries($larabitsHtml);

        foreach ($bits as $bit) {
            Utils::writeln("Getting serie: $bit ...");

            $seriHtml = $this->client->getHtml(LARACASTS_BASE_URL.'/series/'.$bit);

            $serie = Parser::getSerieData($seriHtml);

            $episodeHtml = $this->client->getHtml($serie['path'].'/episodes/1');

            $serie['episodes'] = Parser::getEpisodesData($episodeHtml);

            $seriesCollection->add($serie);
        }

        return $seriesCollection->get();
    }

    public function getFilteredSeries(array $filters): array
    {
        $seriesCollection = new SeriesCollection([]);

        foreach ($filters as $serieSlug => $filteredEpisodes) {
            $seriesHtml = $this->client->getHtml("series/$serieSlug");

            $serie = Parser::getSerieData($seriesHtml);

            $episodeHtml = $this->client->getHtml($serie['path'].'/episodes/1');

            $serie['episodes'] = Parser::getEpisodesData($episodeHtml, $filteredEpisodes);

            $seriesCollection->add($serie);
        }

        return $seriesCollection->get();
    }

    /**
     * Determine is specific series has been changed compared to cached data
     */
    private function isSerieUpdated(SeriesCollection $series, array $serie): bool
    {
        $target = $series->where('slug', $serie['slug'])->first();

        return ! is_null($target) && count($target['episodes']) == $serie['episode_count'];
    }
}
