<?php

namespace App\Vimeo;

use App\Contracts\AsyncDownloader;
use App\Utils\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\FulfilledPromise;

class VimeoDownloader implements AsyncDownloader
{
    private readonly VimeoRepository $repository;

    /** @var Client */
    public $client;

    /** @var int Default concurrency for segment downloads */
    private int $maxConcurrentSegments = 5;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
        $this->repository = new VimeoRepository($this->client);
    }

    /**
     * Synchronous download (backwards compatible)
     */
    public function download($vimeoId, string $filepath): bool
    {
        try {
            $this->downloadInternal((string) $vimeoId, $filepath, $this->maxConcurrentSegments);
            return true;
        } catch (\Exception $e) {
            Utils::writeln("Download failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Async download implementation - returns a promise that resolves immediately after download
     * Note: The actual downloads happen synchronously within the promise for simplicity
     */
    public function downloadAsync(string $sourceId, string $destinationPath, array $options = []): PromiseInterface
    {
        $concurrency = $options['max_concurrent_segments'] ?? $this->maxConcurrentSegments;

        try {
            $this->downloadInternal($sourceId, $destinationPath, $concurrency);
            return new FulfilledPromise($destinationPath);
        } catch (\Exception $e) {
            return \GuzzleHttp\Promise\Create::rejectionFor($e);
        }
    }

    /**
     * Internal download logic - downloads video and audio tracks with parallel segments
     */
    private function downloadInternal(string $vimeoId, string $destinationPath, int $concurrency): void
    {
        $video = $this->repository->get($vimeoId);
        $master = $this->repository->getMaster($video);

        $sources = [];
        $sources[] = $master->getVideoById($video->getVideoIdByQuality());
        $sources[] = $master->getAudio();

        $filenames = [];

        foreach ($sources as $source) {
            $filename = $master->getClipId() . $source['extension'];
            $filenames[] = $filename;

            $this->downloadSource(
                $master->resolveURL($source['base_url']),
                $source,
                $filename,
                $concurrency
            );
        }

        // Merge sources
        $success = $this->mergeSources($filenames[0], $filenames[1], $destinationPath);

        if (!$success) {
            throw new \Exception("Failed to merge video and audio sources");
        }
    }

    /**
     * Download a source (video or audio track) with all its segments in parallel
     */
    private function downloadSource(string $baseURL, array $sourceData, string $filepath, int $concurrency): void
    {
        // Write init segment first
        file_put_contents($filepath, base64_decode((string) $sourceData['init_segment'], true));

        $segmentURLs = array_map(fn($segment): string => $baseURL . $segment['url'], $sourceData['segments']);
        $sizes = array_column($sourceData['segments'], 'size');

        // Download segments in parallel
        $this->downloadSegmentsParallel($segmentURLs, $filepath, $sizes, $concurrency);
    }

    /**
     * Download segments in parallel using Guzzle Pool
     * Segments are downloaded concurrently and merged in order
     */
    private function downloadSegmentsParallel(array $segmentURLs, string $filepath, array $sizes, int $concurrency): void
    {
        $type = str_contains($filepath, 'm4v') ? 'video' : 'audio';
        Utils::writeln("Downloading $type in parallel (concurrency: $concurrency)...");

        $totalBytes = array_sum($sizes);
        $downloadedBytes = 0;
        $segmentCount = count($segmentURLs);

        // Store downloaded segment data indexed by position
        $segments = array_fill(0, $segmentCount, null);

        // Create request generator
        $requests = function () use ($segmentURLs) {
            foreach ($segmentURLs as $index => $url) {
                yield $index => new Request('GET', $url);
            }
        };

        // Use Pool for concurrent downloads
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $index) use (&$segments, $sizes, &$downloadedBytes, $totalBytes) {
                $segments[$index] = $response->getBody()->getContents();
                $downloadedBytes += $sizes[$index];
                Utils::showProgressBar($downloadedBytes, $totalBytes);
            },
            'rejected' => function ($reason, $index) {
                throw new \Exception("Segment $index download failed: " . $reason->getMessage());
            },
        ]);

        // Wait for pool to complete
        $pool->promise()->wait();

        // Merge segments in order
        $handle = fopen($filepath, 'ab');
        foreach ($segments as $segmentData) {
            if ($segmentData !== null) {
                fwrite($handle, $segmentData);
            }
        }
        fclose($handle);

        Utils::writeln(''); // New line after progress bar
    }

    private function mergeSources(string $videoPath, string $audioPath, string $outputPath): bool
    {
        $code = 0;
        $output = [];

        if (PHP_OS === 'WINNT') {
            $command = "ffmpeg -i \"$videoPath\" -i \"$audioPath\" -vcodec copy -acodec copy -strict -2 \"$outputPath\" 2> nul";
        } else {
            $command = "ffmpeg -i '$videoPath' -i '$audioPath' -vcodec copy -acodec copy -strict -2 '$outputPath' >/dev/null 2>&1";
        }

        exec($command, $output, $code);

        if ($code == 0) {
            unlink($videoPath);
            unlink($audioPath);

            return true;
        }

        return false;
    }
}
