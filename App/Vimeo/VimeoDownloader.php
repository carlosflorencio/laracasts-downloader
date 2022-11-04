<?php

namespace App\Vimeo;

use App\Utils\Utils;
use GuzzleHttp\Client;

class VimeoDownloader
{
    /** @var VimeoRepository */
    private $repository;

    /** @var Client */
    public $client;

    public function __construct()
    {
        $this->client = new Client();

        $this->repository = new VimeoRepository($this->client);
    }

    /**
     * @return bool
     */
    public function download($vimeoId, $filepath)
    {
        $video = $this->repository->get($vimeoId);

        $master = $this->repository->getMaster($video);

        $sources = [];
        $sources[] = $master->getVideoById($video->getVideoIdByQuality());
        $sources[] = $master->getAudio();

        $filenames = [];

        foreach ($sources as $source) {
            $filename = $master->getClipId().$source['extension'];
            $this->downloadSource(
                $master->resolveURL($source['base_url']),
                $source,
                $filename
            );
            $filenames[] = $filename;
        }

        return $this->mergeSources($filenames[0], $filenames[1], $filepath);
    }

    private function downloadSource($baseURL, $sourceData, $filepath)
    {
        file_put_contents($filepath, base64_decode($sourceData['init_segment'], true));

        $segmentURLs = array_map(function($segment) use ($baseURL) {
            return $baseURL.$segment['url'];
        }, $sourceData['segments']);

        $sizes = array_column($sourceData['segments'], 'size');

        $this->downloadSegments($segmentURLs, $filepath, $sizes);
    }

    private function downloadSegments($segmentURLs, $filepath, $sizes)
    {
        $type = strpos($filepath, 'm4v') !== false ? 'video' : 'audio';
        Utils::writeln("Downloading $type...");

        $downloadedBytes = 0;

        $totalBytes = array_sum($sizes);

        foreach ($segmentURLs as $index => $segmentURL) {
            $request = $this->client->createRequest('GET', $segmentURL, [
                'save_to' => fopen($filepath, 'a'),
            ]);

            Utils::showProgressBar($request, $downloadedBytes, $totalBytes);

            $this->client->send($request);

            $downloadedBytes += $sizes[$index];
        }
    }

    /**
     * @param  string  $videoPath
     * @param  string  $audioPath
     * @param  string  $outputPath
     *
     * @return bool
     */
    private function mergeSources($videoPath, $audioPath, $outputPath)
    {
        $code = 0;
        $output = [];

        exec("ffmpeg -i '$videoPath' -i '$audioPath' -vcodec copy -acodec copy -strict -2 '$outputPath' >/dev/null 2>&1", $output, $code);

        if ($code == 0) {
            unlink($videoPath);
            unlink($audioPath);

            return true;
        }

        return false;
    }
}
