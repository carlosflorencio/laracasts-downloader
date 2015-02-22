<?php  namespace App\System;

use App\Downloader;
use League\Flysystem\Filesystem;

class Controller {

    /**
     * @var Filesystem
     */
    private $system;

    /**
     * @param Filesystem $system
     */
    function __construct(Filesystem $system)
    {
        $this->system = $system;
    }

    /**
     * Gets the array of the local lessons & series
     * @return array
     */
    public function getAllLessons()
    {
        $array['lessons'] = $this->getLessons();
        $array['series'] = $this->getSeries();

        Downloader::$currentLessonNumber = count($array['lessons']);

        return $array;
    }

    private function getSeries()
    {
        $list = $this->system->listContents(SERIES_FOLDER, true);
        $array = [];

        foreach ($list as $entry) {
            if($entry['type'] != 'file') continue; //skip folder, we only want the files

            $serie = substr($entry['dirname'], strpos($entry['dirname'], '\\') + 1);
            $episode = (int) substr($entry['filename'], 0, strpos($entry['filename'], '-'));

            $array[$serie][] = $episode;
        }

        return $array;
    }

    /**
     * Gets the lessons in the folder
     * @return array
     */
    public function getLessons() {
        $list = $this->system->listContents(LESSONS_FOLDER);
        $array = [];

        foreach ($list as $entry) {
            if($entry['type'] != 'file') continue;

            $originalName = $entry['filename'];

            $array[] = substr($originalName, strpos($originalName, '-') + 1);
        }

        return $array;
    }

    /**
     * Rename lessons, adding 0 padding to the number
     */
    public function renameLessonsWithRightPadding() {
        $list = $this->system->listContents(LESSONS_FOLDER);

        foreach ($list as $entry) {
            if($entry['type'] != 'file') continue;

            $originalName = $entry['basename'];
            $oldNumber = substr($originalName, 0, strpos($originalName, '-'));

            if(strlen($oldNumber) == 4) continue; // already correct

            $newNumber = sprintf("%04d", $oldNumber);
            $nameWithoutNumber = substr($originalName, strpos($originalName, '-') + 1);
            $newName = $newNumber . '-' . $nameWithoutNumber;

            $this->system->rename(LESSONS_FOLDER . '/' . $originalName, LESSONS_FOLDER . '/' . $newName);
        }
    }

    /**
     * Create folder if not exists
     * @param $serie
     */
    public function createSerieFolderIfNotExists($serie)
    {
        if($this->system->has(SERIES_FOLDER . '/' . $serie) == false)
            $this->system->createDir(SERIES_FOLDER . '/' . $serie);
    }

}