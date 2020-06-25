<?php

$appClass = 'Xfrocks\Api\App';

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require(dirname(__DIR__) . '/tests/autoload.php');

$loader->addPsr4('dev\PHPStan\\', __DIR__ . '/PHPStan');

$extensionHintManualPath = realpath(__DIR__ . '/../../_output/extension_hint_manual.php');
$loader->addClassMap([
    'Xfrocks\Api\XFRM\Data\XFCP_Modules' => $extensionHintManualPath,
]);

return $loader;
