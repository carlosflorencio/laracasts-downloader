<?php

/**
 * System Controller
 */

namespace App\System;

use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;

/**
 * Class Controller
 */
class Controller
{
    public function __construct(
        private readonly Filesystem $system
    ) {}

    public function getSeries(): array
    {
        // we want only files, and we only need their paths
        $paths = $this->system->listContents(SERIES_FOLDER, true)
            ->filter(fn (StorageAttributes $attributes): bool => $attributes->isFile())
            ->sortByPath()
            ->map(fn (StorageAttributes $attrs): string => $attrs->path())
            ->toArray();

        $array = [];

        foreach ($paths as $path) {
            $segments = explode('/', substr((string) $path, strlen(SERIES_FOLDER) + 1));

            // this happens on MAC when "series/.DS_Store" is present
            if (! isset($segments[1])) {
                continue;
            }

            [$serie, $episodeName] = $segments;

            $episodeNo = (int) substr($episodeName, 0, strpos($episodeName, '-'));

            $array[$serie][] = $episodeNo;
        }

        return $array;
    }

    public function createSerieFolderIfNotExists(string $serieSlug): void
    {
        $this->createFolderIfNotExists(SERIES_FOLDER.'/'.$serieSlug);
    }

    public function createFolderIfNotExists($folder): void
    {
        if ($this->system->has($folder) === false) {
            $this->system->createDirectory($folder);
        }
    }

    public function setCache(array $data): void
    {
        $file = 'cache.json';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, json_encode($data));
    }

    public function getCache(): array
    {
        $file = 'cache.json';

        return $this->system->fileExists($file)
            ? json_decode($this->system->read($file), true)
            : [];
    }
}
