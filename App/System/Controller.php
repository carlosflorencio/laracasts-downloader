<?php

/**
 * System Controller
 */

namespace App\System;

use App\Utils\Utils;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;

/**
 * Class Controller
 */
class Controller
{
    /**
     * Receives dependencies
     */
    public function __construct(
        /**
         * Flysystem lib
         */
        private readonly Filesystem $system
    ) {}

    /**
     * Get the series
     */
    public function getSeries(bool $skip = false): array
    {
        // we want only files, and we only need their paths
        $list = $this->system->listContents(SERIES_FOLDER, true)
            ->filter(fn (StorageAttributes $attributes) => $attributes->isFile())
            ->sortByPath()
            ->map(fn (StorageAttributes $attrs) => $attrs->path())
            ->toArray();

        $array = [];

        foreach ($list as $path) {
            $dirAndFilename = explode('/', substr((string) $path, strlen(SERIES_FOLDER) + 1));
            $serie = $dirAndFilename[0];
            // this happens on mac when "series/.DS_Store" is present
            if (! isset($dirAndFilename[1])) {
                continue;
            }
            $episodeName = $dirAndFilename[1];
            $episodeNo = (int) substr((string) $episodeName, 0, strpos((string) $episodeName, '-'));

            $array[$serie][] = $episodeNo;
        }

        // TODO: #Issue# returns array with index 0
        if ($skip) {
            foreach ($this->getSkippedSeries() as $skipSerie => $episodes) {
                if (! isset($array[$skipSerie])) {
                    $array[$skipSerie] = $episodes;

                    continue;
                }

                $array[$skipSerie] = array_filter(
                    array_unique(
                        array_merge($array[$skipSerie], $episodes)
                    )
                );
            }
        }

        return $array;
    }

    /**
     * run write commands
     */
    public function writeSkipFiles(): void
    {
        Utils::box('Creating skip files');

        $this->writeSkipSeries();

        Utils::write('Skip files for series created');
    }

    /**
     * Create skip file to lessons
     */
    private function writeSkipSeries(): void
    {
        $file = SERIES_FOLDER.'/.skip';

        $series = serialize($this->getSeries(true));

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $series);
    }

    /**
     * Get skipped series
     *
     * @return array
     */
    private function getSkippedSeries()
    {
        return $this->getSkippedData(SERIES_FOLDER.'/.skip');
    }

    /**
     * Read skip file
     *
     * @return array|mixed
     */
    private function getSkippedData(string $pathToSkipFile)
    {
        if ($this->system->has($pathToSkipFile)) {
            $content = $this->system->read($pathToSkipFile);

            return unserialize($content);
        }

        return [];
    }

    /**
     * Create series folder if not exists.
     */
    public function createSerieFolderIfNotExists(string $serieSlug): void
    {
        $this->createFolderIfNotExists(SERIES_FOLDER.'/'.$serieSlug);
    }

    /**
     * Create folder if not exists.
     */
    public function createFolderIfNotExists($folder): void
    {
        if ($this->system->has($folder) === false) {
            $this->system->createDirectory($folder);
        }
    }

    /**
     * Create cache file
     *
     *
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function setCache(array $data): void
    {
        $file = 'cache.php';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, '<?php return '.var_export($data, true).';'.PHP_EOL);
    }

    /**
     * Get cached items
     *
     * @return array
     */
    public function getCache(): array|string
    {
        $file = 'cache.php';

        return $this->system->has($file)
            ? ''.$file
            : [];
    }
}
