<?php
/**
 * App start point
 */
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;

require_once 'bootstrap.php';

/*
 * Dependencies
 */
$client = new GuzzleHttp\Client(['base_url' => LARACASTS_BASE_URL]);
$filesystem = new Filesystem(new Adapter(BASE_FOLDER));
$bench = new Ubench();
$algolia = new AlgoliaSearch\Client(ALGOLIA_APP_ID, ALGOLIA_API_KEY);

/*
 * App
 */
$app = new App\Downloader($client, $filesystem, $bench, $algolia, RETRY_DOWNLOAD);

try {
    $app->start($options);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage();
}
