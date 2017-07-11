<?php

$startTime = microtime(true);

// we have to figure out XenForo path
// dirname(dirname(__FILE__)) should work most of the time
// as it was the way XenForo's index.php does
// however, sometimes it may not work...
// so we have to be creative
$parentOfDirOfFile = dirname(dirname(__FILE__));
$scriptFilename = (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
$pathToCheck = '/library/XenForo/Autoloader.php';
$fileDir = false;
if (file_exists($parentOfDirOfFile . $pathToCheck)) {
    $fileDir = $parentOfDirOfFile;
}
if ($fileDir === false AND !empty($scriptFilename)) {
    $parentOfDirOfScriptFilename = dirname(dirname($scriptFilename));
    if (file_exists($parentOfDirOfScriptFilename . $pathToCheck)) {
        $fileDir = $parentOfDirOfScriptFilename;
    }
}
if ($fileDir === false) {
    die('XenForo path could not be figured out...');
}

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);
