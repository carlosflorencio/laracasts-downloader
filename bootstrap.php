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
//Algolia
$options['algolia_app_id'] = getenv('ALGOLIA_APP_ID');
$options['algolia_api_key'] = getenv('ALGOLIA_API_KEY');

define('BASE_FOLDER', $options['local_path']);
define('LESSONS_FOLDER', $options['lessons_folder']);
define('SERIES_FOLDER', $options['series_folder']);
define('RETRY_DOWNLOAD', $options['retry_download']);
define('ALGOLIA_APP_ID', $options['algolia_app_id']);
define('ALGOLIA_API_KEY', $options['algolia_api_key']);
define('ALGOLIA_INDEX_NAME', 'lessons');

//laracasts
define('LARACASTS_BASE_URL', 'https://laracasts.com');
define('LARACASTS_LOGIN_PATH', 'login');
define('LARACASTS_POST_LOGIN_PATH', 'sessions');
define('LARACASTS_LESSONS_PATH', 'lessons');
define('LARACASTS_SERIES_PATH', 'series');

/*
 * Vars
 */
set_time_limit(0);
