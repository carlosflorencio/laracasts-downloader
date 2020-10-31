<?php
/**
 * System Controller
 */
namespace App\System;

use App\Downloader;
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
     * Gets the array of the local lessons & series.
     *
     * @return array
     */
    public function getAllLessons()
    {
        $array            = [];
        $array['lessons'] = $this->getLessons(true);
        $array['series']  = $this->getSeries(true);

        Downloader::$totalLocalLessons = count($array['lessons']);

        return $array;
    }

    /**
     * Get the series
     *
     * @param bool $skip
     *
     * @return array
     */
    private function getSeries($skip = false)
    {
        $list  = $this->system->listContents(SERIES_FOLDER, true);
        $array = [];

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            } //skip folder, we only want the files

            $serie   = substr($entry['dirname'], strlen(SERIES_FOLDER) + 1);
            $episode = (int)substr($entry['filename'], 0, strpos($entry['filename'], '-'));

            $array[$serie][] = $episode;
        }

        if($skip) {
            foreach($this->getSkipSeries() as $skipSerie => $episodes) {
                if(!isset($array[$skipSerie])) {
                    $array[$skipSerie] = $episodes;
                    continue;
                }

                $array[$skipSerie] = array_merge($array[$skipSerie], $episodes);
                $array[$skipSerie] = array_filter(array_unique($array[$skipSerie]));
            }
        }

        return $array;
    }

    /**
     * Gets the lessons in the folder.
     *
     * @param bool $skip
     *
     * @return array
     */
    public function getLessons($skip = false)
    {
        $list  = $this->system->listContents(LESSONS_FOLDER);
        $array = [];

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            $originalName = $entry['filename'];

            $array[] = substr($originalName, strpos($originalName, '-') + 1);
        }

        if ($skip) {
            $array = array_merge($this->getSkipLessons(), $array);
            $array = array_filter(array_unique($array));
        }

        return $array;
    }

    /**
     * Create skip file to lessons
     */
    public function writeSkipLessons()
    {
        $file = LESSONS_FOLDER . '/.skip';

        $lessons = serialize($this->getLessons(true));

        if($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $lessons);
    }

    /**
     * run write commands
     */
    public function writeSkipFiles()
    {
        Utils::box('Creating skip files');

        $this->writeSkipSeries();
        Utils::write('Skip files for series created');

        $this->writeSkipLessons();
        Utils::write('Skip files for lesson created');

        Utils::box('Finished');
    }

    /**
     * Create skip file to lessons
     */
    public function writeSkipSeries()
    {
        $file = SERIES_FOLDER . '/.skip';

        $series = serialize($this->getSeries(true));

        if($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $series);
    }

    /**
     * Get skiped lessons
     * @return array
     */
    public function getSkipLessons()
    {
        return $this->getSkipedData(LESSONS_FOLDER . '/.skip');
    }

    /**
     * Get skiped series
     * @return array
     */
    public function getSkipSeries()
    {
        return $this->getSkipedData(SERIES_FOLDER . '/.skip');
    }

    /**
     * Read skip file
     *
     * @param $pathToSkipFile
     * @return array|mixed
     */
    private function getSkipedData($pathToSkipFile) {

        if ($this->system->has($pathToSkipFile)) {
            $content = $this->system->read($pathToSkipFile);

            return unserialize($content);
        }

        return [];
    }

    /**
     * Rename lessons, adding 0 padding to the number.
     */
    public function renameLessonsWithRightPadding()
    {
        $list = $this->system->listContents(LESSONS_FOLDER);

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            $originalName = $entry['basename'];
            $oldNumber    = substr($originalName, 0, strpos($originalName, '-'));

            if (strlen($oldNumber) == 4) {
                continue;
            } // already correct

            $newNumber         = sprintf("%04d", $oldNumber);
            $nameWithoutNumber = substr($originalName, strpos($originalName, '-') + 1);
            $newName           = $newNumber . '-' . $nameWithoutNumber;

            $this->system->rename(LESSONS_FOLDER . '/' . $originalName, LESSONS_FOLDER . '/' . $newName);
        }
    }

    /**
     * Create series folder if not exists.
     *
     * @param $serie
     */
    public function createSerieFolderIfNotExists($serie)
    {
        $this->createFolderIfNotExists(SERIES_FOLDER . '/' . $serie);
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
     */
    public function cacheSeriesData($data)
    {
        $file = 'cache.php';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
    }

    public function getCacheData()
    {
        $file = 'cache.php';

        return $this->system->has($file)
            ? require $this->system->getAdapter()->getPathPrefix() . 'cache.php'
            : null;
    }
}
