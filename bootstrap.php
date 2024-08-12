<?php

/**
 * Composer autoloader.
 */
require 'vendor/autoload.php';

/*
 * Options
 */

$options = array();

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$timezone = getenv('TIMEZONE');

date_default_timezone_set($timezone);

//Login
$options['password'] = getenv('PASSWORD');
$options['email'] = getenv('EMAIL');
//Paths
$options['local_path'] = getenv('LOCAL_PATH');
$options['lessons_folder'] = getenv('LESSONS_FOLDER');
$options['series_folder'] = getenv('SERIES_FOLDER');
//Flags
$options['retry_download'] = boolval(getenv('RETRY_DOWNLOAD'));

define('BASE_FOLDER', $options['local_path']);
define('LESSONS_FOLDER', $options['lessons_folder']);
define('SERIES_FOLDER', $options['series_folder']);
define('RETRY_DOWNLOAD', $options['retry_download']);

define("REQUEST_USER_AGENT", "Mozilla/5.3 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36");


//laracasts
define('LARACASTS_BASE_URL', 'https://laracasts.com');
define('LARACASTS_POST_LOGIN_PATH', 'sessions');
define('LARACASTS_SERIES_PATH', 'series');
define('LARACASTS_TOPICS_PATH', 'browse/all');

/*
 * Vars
 */
set_time_limit(0);
