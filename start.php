<?php
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;

/**
 * Composer autoloader
 */
require 'vendor/autoload.php';

/**
 * Options
 */
$options = parse_ini_file('config.ini');

/**
 * Constants
 */
//local
define('BASE_FOLDER', $options['local_path']);
define('LESSONS_FOLDER', $options['lessons_folder']);
define('SERIES_FOLDER', $options['series_folder']);

//laracasts
define('LARACASTS_BASE_URL', 'https://laracasts.com');
define('LARACASTS_ALL_PATH', 'all');
define('LARACASTS_LOGIN_PATH', 'login');
define('LARACASTS_POST_LOGIN_PATH', 'sessions');
define('LARACASTS_LESSONS_PATH', 'lessons');
define('LARACASTS_SERIES_PATH', 'series');

/**
 * Vars
 */
set_time_limit(0);

/**
 * Dependencies
 */
$client = new GuzzleHttp\Client(['base_url' => LARACASTS_BASE_URL]);
$filesystem = new Filesystem(new Adapter(BASE_FOLDER));
$bench = new Ubench();

/**
 * App
 */
$app = new App\Downloader($client, $filesystem, $bench);
$app->start($options);

// TODO: x of 5 left
// TODO: Downloading series, add the name of the serie and how much episodes of that serie left
// TODO: Maybe a total gb count