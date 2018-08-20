<?php

/** @noinspection PhpIncludeInspection */
require('/var/www/html/src/vendor/autoload.php');

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require(dirname(dirname(__DIR__)) . '/vendor/autoload.php');

$loader->addPsr4('tests\api\\', __DIR__ . '/api');
$loader->addPsr4('tests\bases\\', __DIR__ . '/bases');

return $loader;
