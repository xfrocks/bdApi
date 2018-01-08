<?php

// we have to figure out XenForo path
// dirname(dirname(__FILE__)) should work most of the time
// as it was the way XenForo's index.php does
// however, sometimes it may not work...
// so we have to be creative
$parentOfDirOfFile = dirname(dirname(__FILE__));
$scriptFilename = (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
$pathToXfPhp = '/src/XF.php';
$fileDir = false;
if (file_exists($parentOfDirOfFile . $pathToXfPhp)) {
    $fileDir = $parentOfDirOfFile;
}
if ($fileDir === false && !empty($scriptFilename)) {
    $parentOfDirOfScriptFilename = dirname(dirname($scriptFilename));
    if (file_exists($parentOfDirOfScriptFilename . $pathToXfPhp)) {
        $fileDir = $parentOfDirOfScriptFilename;
    }
}
if ($fileDir === false) {
    die('XenForo path could not be figured out...');
}

/** @noinspection PhpIncludeInspection */
require($fileDir . $pathToXfPhp);
XF::start($fileDir);
