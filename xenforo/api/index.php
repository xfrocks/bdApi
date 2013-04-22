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
if (file_exists($parentOfDirOfFile . $pathToCheck))
{
	$fileDir = $parentOfDirOfFile;
}
if ($fileDir === false AND !empty($scriptFilename))
{
	$parentOfDirOfScriptFilename = dirname(dirname($scriptFilename));
	if (file_exists($parentOfDirOfScriptFilename . $pathToCheck))
	{
		$fileDir = $parentOfDirOfScriptFilename;
	}
}
if ($fileDir === false)
{
	die('XenForo path could not be figured out...');
}

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

// PUT method support
if (isset($_SERVER['REQUEST_METHOD'])
AND ($_SERVER['REQUEST_METHOD'] === 'PUT'
		OR $_SERVER['REQUEST_METHOD'] === 'DELETE'
)
)
{
	$input = file_get_contents('php://input');
	$inputParams = array();
	parse_str($input, $inputParams);
	foreach ($inputParams as $key => $value)
	{
		$_POST[$key] = $value;
		$_REQUEST[$key] = $value;
	}
}

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$fc = new XenForo_FrontController(new bdApi_Dependencies());

XenForo_Application::set('_bdApi_fc', $fc);

$fc->run();