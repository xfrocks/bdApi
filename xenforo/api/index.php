<?php

// method overriding support
if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
    && isset($_SERVER['REQUEST_METHOD'])
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    // support overriding via HTTP header
    // but only with POST requests
    if (in_array($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'], array(
        'PUT',
        'DELETE',
    ))) {
        $_SERVER['REQUEST_METHOD'] = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
    }
}

$input = file_get_contents('php://input');

$inputJson = false;
if (isset($_SERVER['REQUEST_METHOD'])
    && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $inputJson = @json_decode($input, true);
}

if (!empty($inputJson)) {
    // because PHP parse input incorrectly with json payload
    // we have to reset $_POST/$_REQUEST for them
    foreach ($_POST as $postKey => $postValue) {
        if (isset($_GET[$postKey])) {
            $_REQUEST[$postKey] = $_GET[$postKey];
            continue;
        }

        unset($_REQUEST[$postKey]);
    }
    $_POST = array();
} elseif (isset($_SERVER['REQUEST_METHOD'])
    && in_array($_SERVER['REQUEST_METHOD'], array(
        'PUT',
        'DELETE',
    ))
) {
    // PUT, DELETE method support
    // PHP does not parse input unless it is POST request...
    // TODO: security check
    $inputParams = array();
    parse_str($input, $inputParams);
    foreach ($inputParams as $key => $value) {
        $_POST[$key] = $value;
        $_REQUEST[$key] = $value;
    }
}

require('bootstrap.php');

$fc = new XenForo_FrontController(new bdApi_Dependencies());

XenForo_Application::set('_bdApi_fc', $fc);

$fc->run();
