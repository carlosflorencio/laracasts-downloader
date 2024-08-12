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

/*
 * Set default headers
 */
$headers = $client->getDefaultOption('headers');
$headers = array_merge($headers, ['User-Agent' => REQUEST_USER_AGENT, 'Referer' => LARACASTS_BASE_URL]);
$client->setDefaultOption('headers', $headers);


/*
 * App
 */
$app = new App\Downloader($client, $filesystem, $bench, RETRY_DOWNLOAD);

try {
    $app->start($options);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage();
}
