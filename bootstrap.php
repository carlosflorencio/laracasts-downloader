<?php

/**
 * Composer autoloader.
 */
require 'vendor/autoload.php';

/*
 * Options
 */

$options = [];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$timezone = $_ENV['TIMEZONE'];

date_default_timezone_set($timezone);

//Login
$options['password'] = $_ENV['PASSWORD'];
$options['email'] = $_ENV['EMAIL'];
//Paths
$options['local_path'] = $_ENV['LOCAL_PATH'];
$options['lessons_folder'] = $_ENV['LESSONS_FOLDER'];
$options['series_folder'] = $_ENV['SERIES_FOLDER'];

define('BASE_FOLDER', $options['local_path']);
define('LESSONS_FOLDER', $options['lessons_folder']);
define('SERIES_FOLDER', $options['series_folder']);

//laracasts
define('LARACASTS_BASE_URL', 'https://laracasts.com');
define('LARACASTS_POST_LOGIN_PATH', 'sessions');
define('LARACASTS_SERIES_PATH', 'series');
define('LARACASTS_TOPICS_PATH', 'browse/all');

/*
 * Vars
 */
set_time_limit(0);
