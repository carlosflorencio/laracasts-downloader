<?php

/**
 * Composer autoloader.
 */
require 'vendor/autoload.php';

/*
 * Options
 */
$options = parse_ini_file('config.ini');

/*
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

/*
 * Vars
 */
set_time_limit(0);