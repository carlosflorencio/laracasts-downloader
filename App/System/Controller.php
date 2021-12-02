<?php
/**
 * System Controller
 */
namespace App\System;

use App\Utils\Utils;
use League\Flysystem\Filesystem;

/**
 * Class Controller
 * @package App\System
 */
class Controller
{
    /**
     * Flysystem lib
     * @var Filesystem
     */
    private $system;

    /**
     * Receives dependencies
     *
     * @param Filesystem $system
     */
    public function __construct(Filesystem $system)
    {
        $this->system = $system;
    }

    /**
     * Get the series
     *
     * @param bool $skip
     *
     * @return array
     */
    public function getSeries($skip = false)
    {
        $list  = $this->system->listContents(SERIES_FOLDER, true);
        $array = [];

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            //skip folder, we only want the files
            if (substr($entry['filename'], 0, 2) == '._') {
                continue;
            }

            $serie   = substr($entry['dirname'], strlen(SERIES_FOLDER) + 1);
            $episode = (int)substr($entry['filename'], 0, strpos($entry['filename'], '-'));

            $array[$serie][] = $episode;
        }

        // TODO: #Issue# returns array with index 0
        if($skip) {
            foreach($this->getSkippedSeries() as $skipSerie => $episodes) {
                if(!isset($array[$skipSerie])) {
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
    public function writeSkipFiles()
    {
        Utils::box('Creating skip files');

        $this->writeSkipSeries();

        Utils::write('Skip files for series created');
    }

    /**
     * Create skip file to lessons
     */
    private function writeSkipSeries()
    {
        $file = SERIES_FOLDER . '/.skip';

        $series = serialize($this->getSeries(true));

        if($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $series);
    }

    /**
     * Get skipped series
     * @return array
     */
    private function getSkippedSeries()
    {
        return $this->getSkippedData(SERIES_FOLDER . '/.skip');
    }

    /**
     * Read skip file
     *
     * @param $pathToSkipFile
     * @return array|mixed
     */
    private function getSkippedData($pathToSkipFile)
    {
        if ($this->system->has($pathToSkipFile)) {
            $content = $this->system->read($pathToSkipFile);

            return unserialize($content);
        }

        return [];
    }

    /**
     * Create series folder if not exists.
     *
     * @param $serieSlug
     */
    public function createSerieFolderIfNotExists($serieSlug)
    {
        $this->createFolderIfNotExists(SERIES_FOLDER . '/' . $serieSlug);
    }

    /**
     * Create folder if not exists.
     *
     * @param $folder
     */
    public function createFolderIfNotExists($folder)
    {
        if ($this->system->has($folder) === false) {
            $this->system->createDir($folder);
        }
    }

    /**
     * Create cache file
     *
     * @param array $data
     * @throws \League\Flysystem\FileExistsException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function setCache($data)
    {
        $file = 'cache.php';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
    }

    /**
     * Get cached items
     *
     * @return array
     */
    public function getCache()
    {
        $file = 'cache.php';

        return $this->system->has($file)
            ? require $this->system->getAdapter()->getPathPrefix() . $file
            : [];
    }
}
