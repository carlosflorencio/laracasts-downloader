<?php

namespace App\Contracts;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Interface AsyncDownloader
 *
 * All downloaders (Vimeo, Laracasts) must implement this interface
 * to support the parallel download queue.
 */
interface AsyncDownloader
{
    /**
     * Initiates the download process and returns a promise.
     * The promise resolves when the file is fully downloaded and (if needed) merged.
     *
     * @param string $sourceId Identifier for the source (e.g., Vimeo ID or URL)
     * @param string $destinationPath Full path where the file should be saved
     * @param array $options Optional configuration (e.g., ['max_concurrent_segments' => 5])
     * @return PromiseInterface Resolves to the destination path on success
     */
    public function downloadAsync(string $sourceId, string $destinationPath, array $options = []): PromiseInterface;
}
