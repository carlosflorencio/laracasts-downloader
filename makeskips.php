<?php

/**
 * App start point
 */
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as Adapter;
use App\System\Controller;

require_once 'bootstrap.php';

$filesystem = new Filesystem(new Adapter(BASE_FOLDER));

(new Controller($filesystem))->writeSkipFiles();
