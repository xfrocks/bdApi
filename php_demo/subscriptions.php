<?php

require_once('functions.php');

if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_REQUEST['hub_challenge'])) {
        // intent verification, just echo back the challenge
        die($_REQUEST['hub_challenge']);
    }
} elseif (!empty($_REQUEST['fwd'])) {
    // real callback
    $contents = file_get_contents('php://input');
    makeCurlPost($_REQUEST['fwd'], $contents);
}