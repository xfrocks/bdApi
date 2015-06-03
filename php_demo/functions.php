<?php

session_start();

function loadConfiguration()
{
    $config = array();

    $path = dirname(__FILE__) . '/config.php';
    if (file_exists($path)) {
        require($path);
        if (empty($config['api_root'])) {
            $config = array();
        }
    }

    if (!empty($_SESSION['ignore_config'])) {
        $oldConfig = $config;
        $config = $_SESSION;

        if (!empty($oldConfig['api_root'])) {
            $config['placeholder'] = $oldConfig;
        }
    }

    return array_merge(array(
        'api_root' => '',
        'api_key' => '',
        'api_secret' => '',
        'api_scope' => '',

        'placeholder' => array(
            'api_root' => 'http://domain.com/xenforo/api',
            'api_key' => 'abc123',
            'api_secret' => 'xyz456',
            'api_scope' => 'read',
        ),

        'ignore_config' => false,
    ), $config);
}

function displaySetup()
{
    require(dirname(__FILE__) . '/setup.php');
    exit;
}

function getBaseUrl()
{
    // idea from http://stackoverflow.com/questions/6768793/get-the-full-url-in-php
    $ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? true : false;
    $sp = strtolower($_SERVER['SERVER_PROTOCOL']);
    $protocol = substr($sp, 0, strpos($sp, '/')) . (($ssl) ? 's' : '');

    $port = $_SERVER['SERVER_PORT'];
    $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ':' . $port;

    // using HTTP_POST may have some security implication
    $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null);
    $host = isset($host) ? $host : $_SERVER['SERVER_NAME'] . $port;

    $baseUrl = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
    $baseUrl = preg_replace('#\?.*$#', '', $baseUrl);
    $baseUrl = rtrim($baseUrl, '/');

    return $baseUrl;
}

function getCallbackUrl()
{
    return sprintf(
        '%s?action=callback',
        getBaseUrl()
    );
}

function generateJsSdkUrl($apiRoot)
{
    $url = sprintf(
        '%s/index.php?assets/sdk.js',
        $apiRoot
    );

    return $url;
}

function generateOneTimeToken($apiKey, $apiSecret, $userId = 0, $accessToken = '', $ttl = 86400)
{
    $timestamp = time() + $ttl;
    $once = md5($userId . $timestamp . $accessToken . $apiSecret);

    return sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $apiKey);
}

function makeRequest($url, $apiRoot, $accessToken)
{
    if (strpos($url, $apiRoot) === false) {
        $url = sprintf(
            '%s/index.php?%s&oauth_token=%s',
            $apiRoot,
            $url,
            rawurlencode($accessToken)
        );
    }

    $body = @file_get_contents($url);
    $json = @json_decode($body, true);

    return array($body, $json);
}

function makeSubscriptionRequest($config, $topic, $fwd, $accessToken = null)
{
    $subscriptionUrl = sprintf(
        '%s/index.php?subscriptions',
        $config['api_root']
    );

    $callbackUrl = sprintf(
        '%s/subscriptions.php?fwd=%s',
        rtrim(preg_replace('#index.php$#', '', getBaseUrl()), '/'),
        rawurlencode($fwd)
    );

    $postFields = array(
        'hub.callback' => $callbackUrl,
        'hub.mode' => !empty($accessToken) ? 'subscribe' : 'unsubscribe',
        'hub.topic' => $topic,
        'oauth_token' => $accessToken,
        'client_id' => $config['api_key'],
    );

    return array('response' => makeCurlPost($subscriptionUrl, $postFields, false));
}

function makeCurlPost($url, $postFields, $getJson = true)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $body = curl_exec($ch);
    curl_close($ch);

    if (!$getJson) {
        return $body;
    }

    $json = @json_decode($body, true);
    if (empty($json)) {
        die('Unexpected response from server: ' . $body);
    }

    return $json;
}

function renderMessageForPostRequest($url, array $postFields)
{
    $message = 'It looks like you are testing a local installation. ';
    $message .= 'Since this test server cannot reach yours, please run this command in your terminal ';
    $message .= '(or equivalent) please:<br /><br />';
    $message .= '<div class="code">curl -XPOST "' . $url . '" \\</div>';

    $postFieldKeys = array_keys($postFields);
    $lastFieldKey = array_pop($postFieldKeys);
    foreach ($postFields as $postFieldKey => $postFieldValue) {
        $message .= sprintf(
            '<div class="code"> -F %s=%s%s</div>',
            $postFieldKey,
            $postFieldValue,
            $postFieldKey === $lastFieldKey ? '' : ' \\'
        );
    }

    return $message;
}

function renderMessageForJson($url, array $json)
{
    global $accessToken;
    $html = str_replace(' ', '&nbsp;&nbsp;', var_export($json, true));

    if (!empty($accessToken)) {
        $offset = 0;
        while (true) {
            if (preg_match('#\'(?<link>http[^\']+)\'#', $html, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $offset = $matches[0][1] + strlen($matches[0][0]);
                $link = $matches['link'][0];
                $replacement = null;

                if (strpos($link, $accessToken) !== false) {
                    // found a link
                    $targetUrl = sprintf(
                        '%s?action=request&url=%s&access_token=%s',
                        getBaseUrl(),
                        rawurlencode($link),
                        rawurlencode($accessToken)
                    );

                    $replacement = sprintf('<a href="%s">%s</a>', $targetUrl, $link);
                } elseif (substr($link, 0, 4) === 'http') {
                    $replacement = sprintf('<a href="%1$s" target="_blank">%1$s</a>', $link);
                }

                if (!empty($replacement)) {
                    $html = substr_replace(
                        $html,
                        $replacement,
                        $matches['link'][1],
                        strlen($matches['link'][0])
                    );
                    $offset = $matches[0][1] + strlen($replacement);
                }
            } else {
                break;
            }
        }
    }

    return sprintf(
        '<div class="request">Sent Request: %s</div><div class="response">Received Response: %s</div>',
        $url,
        nl2br($html)
    );
}

function renderAccessTokenMessage($tokenUrl, array $json)
{
    global $config, $accessToken;

    if (!empty($json['access_token'])) {
        $accessToken = $json['access_token'];
        $message = sprintf(
            'Obtained access token successfully!<br />'
            . 'Scopes: %s<br />'
            . 'Expires At: %s<br />',
            $json['scope'],
            date('c', time() + $json['expires_in'])
        );

        if (!empty($json['refresh_token'])) {
            $message .= sprintf('Refresh Token: <a href="index.php?action=refresh&refresh_token=%1$s">%1$s</a><br />', $json['refresh_token']);
        } else {
            $message .= sprintf('Refresh Token: N/A<br />');
        }

        list($body, $json) = makeRequest('index', $config['api_root'], $accessToken);
        if (!empty($json['links'])) {
            $message .= '<hr />' . renderMessageForJson('index', $json);
        }
    } else {
        $message = renderMessageForJson($tokenUrl, $json);
    }

    return $message;
}

function isLocal($apiRoot) {
    $apiRootHost = parse_url($apiRoot, PHP_URL_HOST);
    $isLocal = in_array($apiRootHost, array(
        'localhost',
        '127.0.0.1',
        'local.dev',
    ));

    return $isLocal;
}

function bitlyShorten($token, $url)
{
    $bitlyUrl = sprintf(
        '%s?access_token=%s&longUrl=%s&domain=j.mp&format=txt',
        'https://api-ssl.bitly.com/v3/shorten',
        rawurlencode($token),
        rawurlencode($url)
    );

    $body = @file_get_contents($bitlyUrl);
    if (!empty($body)) {
        $url = $body;
    }

    return $url;
}