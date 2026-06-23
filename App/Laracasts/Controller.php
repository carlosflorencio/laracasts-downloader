<?php

namespace App\Laracasts;

use App\Html\Parser;
use App\Http\Resolver;
use App\Utils\SeriesCollection;
use App\Utils\Utils;

class Controller
{
    public function __construct(private readonly Resolver $client) {}

    /**
     *  Gets all series using scraping
     *  2025-08-07: Larabits are included in the Series API and no need additional processing
     */
    public function getSeries(array $cachedData, bool $cacheOnly = false): array
    {
        $seriesCollection = new SeriesCollection($cachedData);

        if ($cacheOnly) {
            return $seriesCollection->get();
        }

        $page = 1;

        do {
            $series = $this->client->getSeries($page);

            foreach ($series['data'] as $serie) {
                if ($this->isSerieUpdated($seriesCollection, $serie)) {
                    continue;
                }

                Utils::writeln("Getting serie: {$serie['slug']} ...");

                $episodeHtml = $this->client->getHtml($serie['path'].'/episodes/1');

                $serie['episodes'] = Parser::getEpisodesData($episodeHtml);

                $seriesCollection->add($serie);
            }

            $page = $series['has_more'] ? $page + 1 : -1;
        } while ($page > 0);

        return $seriesCollection->get();
    }

    public function getFilteredSeries(array $filters): array
    {
        $seriesCollection = new SeriesCollection([]);

        foreach ($filters as $serieSlug => $filteredEpisodes) {
            Utils::writeln("Getting serie: $serieSlug ...");

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
