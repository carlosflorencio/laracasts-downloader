<?php

/**
 * App start point
 */
use App\System\Controller;
use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\Filesystem;

require_once 'bootstrap.php';

$filesystem = new Filesystem(new Adapter(BASE_FOLDER));

(new Controller($filesystem))->writeSkipFiles();
