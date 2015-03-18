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
    $baseUrl = $_SERVER['SCRIPT_URI'];
    $baseUrl = preg_replace('#\?.*$#', '', $baseUrl);
    $baseUrl = rtrim($baseUrl, '/');

    return $baseUrl;
}

function getCallbackUrl()
{
    global $apiRoot, $apiKey, $apiSecret, $apiScope;

    return sprintf(
        '%s?action=callback&api_root=%s&api_key=%s&api_secret=%s&api_scope=%s',
        getBaseUrl(),
        rawurlencode($apiRoot),
        rawurlencode($apiKey),
        rawurlencode($apiSecret),
        rawurlencode($apiScope)
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
                        '%s?action=request&url=%s&access_token=%s&api_root=%s&api_key=%s&api_secret=%s&api_scope=%s',
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