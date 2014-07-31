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

require ($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

// method overriding support
if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) AND isset($_SERVER['REQUEST_METHOD']) AND $_SERVER['REQUEST_METHOD'] === 'POST')
{
	// support overriding via HTTP header
	// but only with POST requests
	if (in_array($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'], array(
		'PUT',
		'DELETE',
	)))
	{
		$_SERVER['REQUEST_METHOD'] = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
	}
}

$input = file_get_contents('php://input');

$inputJson = false;
if (isset($_SERVER['REQUEST_METHOD']) AND $_SERVER['REQUEST_METHOD'] === 'POST')
{
	$inputJson = @json_decode($input, true);
}

if (!empty($inputJson))
{
	// because PHP parse input incorrectly with json payload
	// we have to reset $_POST/$_REQUEST for them
	foreach ($_POST as $postKey => $postValue)
	{
		unset($_REQUEST[$postKey]);
	}
	$_POST = array();
}
elseif (isset($_SERVER['REQUEST_METHOD']) AND in_array($_SERVER['REQUEST_METHOD'], array(
	'PUT',
	'DELETE',
)))
{
	// PUT, DELETE method support
	// PHP does not parse input unless it is POST request...
	// TODO: security check
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
