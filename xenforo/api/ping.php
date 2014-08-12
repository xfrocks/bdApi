<?php

require ('bootstrap.php');

$dependencies = new XenForo_Dependencies_Public();
$dependencies->preLoadData();

$pingQueueModel = XenForo_Model::create('bdApi_Model_PingQueue');

$queueRecords = $pingQueueModel->getQueue();
$pingQueueModel->ping($queueRecords);
