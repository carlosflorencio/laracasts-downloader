<?php

/**
 * App start point
 */
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter as Adapter;

require_once 'bootstrap.php';

/*
 * Dependencies
 */
$client = new GuzzleHttp\Client(['base_uri' => LARACASTS_BASE_URL]);
$filesystem = new Filesystem(new Adapter(BASE_FOLDER));
$bench = new Ubench;

/*
 * App
 */
$app = new App\Downloader($client, $filesystem, $bench);

try {
    $app->start($options);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage();
}
