<?php
/**
 * Algolia Controller
 */
namespace App\Algolia;

use AlgoliaSearch\Client;
use App\Downloader;
use App\Exceptions\AlgoliaException;

/**
 * Class Controller
 * @package App\Algolia
 */
class Controller
{
    /**
     * Client lib
     * @var Client
     */
    private $client;

    /**
     * Receives dependencies
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Grabs all lessons & series from the algolia api.
     *
     * @return array
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function getAllLessons()
    {
        $index = $this->client->initIndex(ALGOLIA_INDEX_NAME);

        $page = 0;
        $params = [
            'facetFilters' => [
                [
                    'type:lesson',
                    'type:series',
                ],
            ],
            'attributesToRetrieve' =>  [
                'path',
                'type',
                'slug',
                'episode_count',
            ],
            'page' => $page,
        ];

        $array = [
            'lessons' => [],
            'series' => [],
        ];

        do {
            try {
                $res = $index->search('', $params);
            } catch (\Exception $e) {
                throw new AlgoliaException($e->getMessage(), $e->getCode(), $e);
            }
            $params['page'] = $res['page'] + 1;

            foreach ($res['hits'] as $lessonInfo) {
                switch ($lessonInfo['type']) {
                    case 'lesson':
                        $path = $lessonInfo['path'];
                        if (preg_match('/'.LARACASTS_LESSONS_PATH.'\/(.+)/', $path, $matches)) { // lesson
                            $array['lessons'][] = $matches[1];
                        }
                        break;
                    case 'series':
                        $serieSlug = $lessonInfo['slug'];
                        foreach (range(1, $lessonInfo['episode_count']) as $episode) {
                            $array['series'][$serieSlug][] = $episode;
                        }
                        break;
                    default:
                        break;
                }
            }

        } while ($res['page'] <= $res['nbPages']);

        Downloader::$currentLessonNumber = count($array['lessons']);

        return $array;
    }

}