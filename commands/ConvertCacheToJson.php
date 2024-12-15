<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter as Adapter;

require_once __DIR__.'/../bootstrap.php';

$downloadFolderPath = __DIR__.'/../'.rtrim(BASE_FOLDER, '/');

$items = require $downloadFolderPath.'/cache.php';
$data = json_encode($items);

$filesystem = new Filesystem(new Adapter($downloadFolderPath));
$filesystem->write('cache.json', $data);
