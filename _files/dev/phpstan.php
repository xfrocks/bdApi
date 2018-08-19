<?php

$appClass = 'Xfrocks\Api\App';

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require(dirname(__DIR__) . '/tests/autoload.php');

$loader->addPsr4('dev\PHPStan\\', __DIR__ . '/PHPStan');

return $loader;
