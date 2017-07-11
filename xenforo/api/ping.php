<?php

require('bootstrap.php');

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

/** @var bdApi_Model_PingQueue $queueModel */
$queueModel = XenForo_Model::create('bdApi_Model_PingQueue');
$targetRunTime = XenForo_Application::getConfig()->get('rebuildMaxExecution');
$hasMore = $queueModel->runQueue($targetRunTime);

header('Content-Type: application/json');
die(sprintf('{"moreDeferred":%s}', $hasMore ? 'true': 'false'));
