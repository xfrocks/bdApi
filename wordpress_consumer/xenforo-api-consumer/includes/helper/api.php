<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_api_getLastErrors()
{
    if (!empty($GLOBALS['_xfac_api_lastErrors'])) {
        return $GLOBALS['_xfac_api_lastErrors'];
    }

    return false;
}

function xfac_api_getModules($config)
{
    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?oauth_token=%s',
        rtrim($config['root'], '/'),
        rawurlencode(xfac_api_generateOneTimeToken($config)),
    )));
    extract($curl);

    if (isset($parts['system_info']['api_modules'])) {
        return $parts['system_info']['api_modules'];
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getVersionSuggestionText($config, $meta)
{
    $requiredModules = array(
        'forum' => 2015030901,
        'oauth2' => 2015030902,
        'subscription' => 2014092301,
        'search/indexing' => 2015091501,
    );

    if (empty($config)) {
        return __('Enter API credentials to start using the plugin.', 'xenforo-api-consumer');
    }

    if (empty($meta['modules'])) {
        return __('Unable to determine API version.', 'xenforo-api-consumer');
    }

    $problems = array();

    foreach ($requiredModules as $module => $moduleVersion) {
        if (empty($meta['modules'][$module])) {
            $problems[] = call_user_func_array('sprintf', array(
                __('Required module %1$s not found.', 'xenforo-api-consumer'),
                $module
            ));
        } elseif ($meta['modules'][$module] < $moduleVersion) {
            $problems[] = call_user_func_array('sprintf', array(
                __('Module %1$s is too old (%3$s < %2$s).', 'xenforo-api-consumer'),
                $module,
                $moduleVersion,
                $meta['modules'][$module],
            ));
        }
    }

    if (!empty($problems)) {
        return implode('<br />', $problems);
    } else {
        return __('All required API modules have been found.', 'xenforo-api-consumer');
    }
}

function xfac_api_hasModuleVersion($config, $module, $version = 0)
{
    $meta = xfac_option_getMeta($config);
    if (empty($meta['modules'])) {
        return false;
    }

    if (!isset($meta['modules'][$module])) {
        return false;
    }

    if ($version > 0 && $meta['modules'][$module] < $version) {
        return false;
    }

    return true;
}

function xfac_api_getAuthorizeUrl($config, $redirectUri, $scope = '')
{
    return call_user_func_array('sprintf', array(
        '%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=%s',
        rtrim($config['root'], '/'),
        rawurlencode($config['clientId']),
        rawurlencode($redirectUri),
        rawurlencode($scope ? $scope : XFAC_API_SCOPE),
    ));
}

function xfac_api_getSdkJsUrl($config)
{
    return call_user_func_array('sprintf', array(
        '%s/index.php?assets/sdk&prefix=xfac',
        rtrim($config['root'], '/'),
    ));
}

function xfac_api_getLoginLink($config, $accessToken, $redirectUri)
{
    if (!parse_url($redirectUri, PHP_URL_HOST)) {
        // host is missing from the redirect uri
        // assume it is a relative one
        $redirectUri = site_url($redirectUri);
    }

    return call_user_func_array('sprintf', array(
        '%s/index.php?tools/login&oauth_token=%s&redirect_uri=%s',
        rtrim($config['root'], '/'),
        rawurlencode($accessToken),
        rawurlencode($redirectUri),
    ));
}

function xfac_api_getLogoutLink($config, $accessToken, $redirectUri)
{
    return call_user_func_array('sprintf', array(
        '%s/index.php?tools/logout&oauth_token=%s&redirect_uri=%s',
        rtrim($config['root'], '/'),
        rawurlencode($accessToken),
        rawurlencode($redirectUri),
    ));
}

function xfac_api_getPublicLink($config, $route)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?tools/link',
        rtrim($config['root'], '/')
    ));
    $postFields = array(
        'oauth_token' => xfac_api_generateOneTimeToken($config),
        'type' => 'public',
        'route' => $route,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (!empty($parts['link'])) {
        return $parts['link'];
    } else {
        return _xfac_api_getFailedResponse($parts);
    }
}

function xfac_api_getAccessTokenFromCode($config, $code, $redirectUri)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?oauth/token/',
        rtrim($config['root'], '/')
    ));
    $postFields = array(
        'grant_type' => 'authorization_code',
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'code' => $code,
        'redirect_uri' => $redirectUri,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);

    return _xfac_api_prepareAccessTokenBody($curl['body']);
}

function xfac_api_getAccessTokenFromRefreshToken($config, $refreshToken)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?oauth/token/',
        rtrim($config['root'], '/')
    ));
    $postFields = array(
        'grant_type' => 'refresh_token',
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'refresh_token' => $refreshToken,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);

    return _xfac_api_prepareAccessTokenBody($curl['body']);
}

function xfac_api_getAccessTokenFromUsernamePassword($config, $username, $password)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?oauth/token/',
        rtrim($config['root'], '/')
    ));
    $postFields = array(
        'grant_type' => 'password',
        'client_id' => $config['clientId'],
        'client_secret' => $config['clientSecret'],
        'username' => $username,
        'password' => $password,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);

    return _xfac_api_prepareAccessTokenBody($curl['body']);
}

function xfac_api_generateOneTimeToken($config, $userId = 0, $accessToken = '', $ttl = 10)
{
    $timestamp = time() + $ttl;
    $once = md5($userId . $timestamp . $accessToken . $config['clientSecret']);

    return sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $config['clientId']);
}

function xfac_api_getForums($config, $accessToken = '', $extraParams = '')
{
    if ($accessToken === '') {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?forums/&oauth_token=%s%s',
        rtrim($config['root'], '/'),
        rawurlencode($accessToken),
        !empty($extraParams) ? '&' . $extraParams : '',
    )));
    extract($curl);

    if (isset($parts['forums'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getUsersMe($config, $accessToken, $autoSubscribe = true)
{
    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?users/me/&oauth_token=%s',
        rtrim($config['root'], '/'),
        rawurlencode($accessToken)
    )));
    extract($curl);

    if (isset($parts['user'])) {
        $parts['_headerLinkHub'] = _xfac_api_getHeaderLinkHub($curl);

        if ($autoSubscribe AND empty($parts['subscription_callback']) AND !empty($parts['_headerLinkHub'])) {
            xfac_api_postSubscription($config, $accessToken, $parts['_headerLinkHub']);
        }

        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getThreadsInForums($config, $forumIds, $accessToken = '', $extraParams = '')
{
    if ($accessToken === '') {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?threads/&forum_id=%s&order=thread_create_date_reverse&oauth_token=%s%s',
        rtrim($config['root'], '/'),
        is_array($forumIds) ? implode(',', $forumIds) : $forumIds,
        rawurlencode($accessToken),
        !empty($extraParams) ? '&' . $extraParams : ''
    )));
    extract($curl);

    if (isset($parts['threads'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getPostsInThread($config, $threadId, $accessToken = '')
{
    if ($accessToken === '') {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?posts/&thread_id=%d&order=natural_reverse&oauth_token=%s',
        rtrim($config['root'], '/'),
        $threadId,
        rawurlencode($accessToken)
    )));
    extract($curl);

    if (isset($parts['posts'])) {
        $parts['_headerLinkHub'] = _xfac_api_getHeaderLinkHub($curl);

        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getPost($config, $postId, $accessToken = '')
{
    if ($accessToken === '') {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?posts/%d/&oauth_token=%s',
        rtrim($config['root'], '/'),
        $postId,
        rawurlencode($accessToken)
    )));
    extract($curl);

    if (isset($parts['post'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getForumFollowed($config, $accessToken)
{
    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?forums/followed&oauth_token=%s',
        rtrim($config['root'], '/'),
        rawurlencode($accessToken),
    )));
    extract($curl);

    if (isset($parts['forums'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getNotifications($config, $accessToken)
{
    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?notifications&oauth_token=%s',
        rtrim($config['root'], '/'),
        rawurlencode($accessToken),
    )));
    extract($curl);

    if (isset($parts['notifications'])) {
        $parts['_headerLinkHub'] = _xfac_api_getHeaderLinkHub($curl);

        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getThread($config, $threadId, $accessToken = '')
{
    if ($accessToken === '') {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?threads/%d/&oauth_token=%s',
        rtrim($config['root'], '/'),
        $threadId,
        rawurlencode($accessToken)
    )));
    extract($curl);

    if (isset($parts['thread'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getUserGroups($config, $userId = 0, $accessToken = '')
{
    if ($accessToken === '') {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?users/%sgroups/&oauth_token=%s',
        rtrim($config['root'], '/'),
        $userId > 0 ? ($userId . '/') : '',
        rawurlencode($accessToken),
    )));
    extract($curl);

    if (isset($parts['user_groups'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postThread($config, $accessToken, $forumId, $threadTitle, $postBody)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?threads/',
        rtrim($config['root'], '/')
    ));
    $postFields = array(
        'oauth_token' => $accessToken,
        'forum_id' => $forumId,
        'thread_title' => $threadTitle,
        'post_body_html' => $postBody,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['thread'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postPost($config, $accessToken, $threadId, $postBody, array $extraParams = array())
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?posts/',
        rtrim($config['root'], '/')
    ));
    $postFields = array_merge(array(
        'oauth_token' => $accessToken,
        'thread_id' => $threadId,
        'post_body_html' => $postBody,
    ), $extraParams);
    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['post'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postUser($config, $email, $username, $password, array $extraParams = array(), $autoSubscribe = true)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?users/',
        rtrim($config['root'], '/')
    ));
    $postFields = array_merge(array(
        'email' => $email,
        'username' => $username,
    ), $extraParams);
    if (empty($postFields['oauth_token'])) {
        $postFields['client_id'] = $config['clientId'];
    }
    $postFields = _xfac_api_encrypt($config, $postFields, 'password', $password);

    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['user'])) {
        $parts['_headerLinkHub'] = _xfac_api_getHeaderLinkHub($curl);

        if ($autoSubscribe AND !empty($parts['token']['access_token']) AND !empty($parts['_headerLinkHub'])) {
            xfac_api_postSubscription($config, $parts['token']['access_token'], $parts['_headerLinkHub']);
        }

        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postSubscription($config, $accessToken, $url, $mode = 'subscribe')
{
    $postFields = array(
        'oauth_token' => $accessToken,
        'hub.callback' => site_url('wp-trackback.php?xfac_callback=1'),
        'hub.mode' => $mode,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);

    return $curl['http_code'] == 202;
}

function xfac_api_postForumFollower($config, $accessToken, $forumId)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?forums/%d/followers',
        rtrim($config['root'], '/'),
        $forumId,
    ));
    $postFields = array('oauth_token' => $accessToken);
    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['status']) AND $parts['status'] == 'ok') {
        return true;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postUserGroups($config, $accessToken, $userId, $primaryGroupId, array $secondaryGroupIds = array())
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?users/%d/groups',
        rtrim($config['root'], '/'),
        $userId,
    ));
    $postFields = array(
        'oauth_token' => $accessToken,
        'primary_group_id' => $primaryGroupId,
        'secondary_group_ids' => $secondaryGroupIds,
    );

    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['status']) AND $parts['status'] == 'ok') {
        return true;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postUserPassword($config, $accessToken, $userId, $password)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?users/%d/password',
        rtrim($config['root'], '/'),
        $userId,
    ));
    $postFields = array(
        'oauth_token' => $accessToken,
        'password' => $password,
    );

    // TODO: add password_old to change password
    // TODO: use password_algo for security

    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['status']) AND $parts['status'] == 'ok') {
        return true;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_putPost($config, $accessToken, $postId, $postBody, array $extraParams = array())
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?posts/%d',
        rtrim($config['root'], '/'),
        $postId,
    ));
    $postFields = array_merge(array(
        'oauth_token' => $accessToken,
        'post_body_html' => $postBody,
    ), $extraParams);
    $curl = _xfac_api_curl($url, 'PUT', $postFields);
    extract($curl);

    if (isset($parts['post'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_deletePost($config, $accessToken, $postId)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?posts/%d&oauth_token=%s',
        rtrim($config['root'], '/'),
        $postId,
        rawurlencode($accessToken),
    ));
    $curl = _xfac_api_curl($url, 'DELETE');
    extract($curl);

    if (isset($parts['post'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_deleteForumFollower($config, $accessToken, $forumId)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?forums/%d/followers&oauth_token=%s',
        rtrim($config['root'], '/'),
        $forumId,
        rawurlencode($accessToken),
    ));
    $curl = _xfac_api_curl($url, 'DELETE');
    extract($curl);

    if (isset($parts['status']) AND $parts['status'] == 'ok') {
        return true;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_getUsersFind($config, $username, $email = '', $adminAccessToken = '')
{
    $curl = _xfac_api_curl(call_user_func_array('sprintf', array(
        '%s/index.php?users/find/&username=%s&email=%s&oauth_token=%s',
        rtrim($config['root'], '/'),
        rawurlencode($username),
        rawurlencode($email),
        rawurlencode($adminAccessToken),
    )));
    extract($curl);

    if (isset($parts['users'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postOauthTokenAdmin($config, $adminAccessToken, $userId)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?oauth/token/admin',
        rtrim($config['root'], '/'),
    ));
    $postFields = array(
        'oauth_token' => $adminAccessToken,
        'user_id' => $userId,
    );
    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    /** @noinspection PhpUndefinedVariableInspection */
    return _xfac_api_prepareAccessTokenBody($body);
}

function xfac_api_putUser($config, $accessToken, $userId, array $postFields)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?users/%d&oauth_token=%s',
        rtrim($config['root'], '/'),
        $userId,
        rawurlencode($accessToken),
    ));

    $curl = _xfac_api_curl($url, 'PUT', $postFields);
    extract($curl);

    if (isset($parts['status']) AND $parts['status'] == 'ok') {
        return true;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postSearchIndexing($config, $accessToken, $contentType, $contentId,
                                     $title, $body, $date, $link)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?search/indexing',
        rtrim($config['root'], '/'),
    ));
    $postFields = array(
        'oauth_token' => $accessToken,
        'content_type' => $contentType,
        'content_id' => $contentId,
        'title' => $title,
        'body' => $body,
        'date' => $date,
        'link' => $link,
    );

    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['status'])
        && $parts['status'] == 'ok'
    ) {
        return true;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_postSearchThreads($config, $accessToken, $q, $limit = 5)
{
    $url = call_user_func_array('sprintf', array(
        '%s/index.php?search/threads',
        rtrim($config['root'], '/'),
    ));
    $postFields = array(
        'oauth_token' => $accessToken,
        'q' => $q,
        'data_limit' => $limit,
    );

    $curl = _xfac_api_curl($url, 'POST', $postFields);
    extract($curl);

    if (isset($parts['data'])) {
        return $parts;
    } else {
        return _xfac_api_getFailedResponse($curl);
    }
}

function xfac_api_filterHtmlFromXenForo($html)
{
    $offset = 0;
    while (true) {
        if (preg_match('#<img[^>]+mceSmilie[^>]+alt="([^"]+)"[^>]+>#', $html, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            // replace smilies with their text representation
            $html = substr_replace($html, $matches[1][0], $matches[0][1], strlen($matches[0][0]));
            $offset = $matches[0][1] + 1;
        } else {
            break;
        }
    }

    return $html;
}

function _xfac_api_curlHeaderFunction($ch, $headerLine)
{
    $GLOBALS['_xfac_api_curlHeaders'][] = trim($headerLine);

    return strlen($headerLine);
}

function _xfac_api_curl($url, $method = 'GET', $postFields = null, $curlOptions = array(), $xfacOptions = array())
{
    $ch = curl_init();
    $requestHeaders = array();
    $GLOBALS['_xfac_api_curlHeaders'] = array();

    $serverIp = get_option('xfac_server_ip');
    if (!empty($serverIp)) {
        // we need to connect directly to this address
        $urlHost = parse_url($url, PHP_URL_HOST);
        if (!empty($urlHost)) {
            $urlHostPos = strpos($url, $urlHost);
            if ($urlHostPos !== false) {
                $url = substr_replace($url, $serverIp, $urlHostPos, strlen($urlHost));
                $requestHeaders['Host'] = 'Host: ' . $urlHost;
            }
        }
    }
    curl_setopt($ch, CURLOPT_URL, $url);

    if (!!get_option('xfac_curl_verify_off')) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    switch ($method) {
        case 'GET':
            // default is GET
            break;
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            break;
        default:
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            break;
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, array_values($requestHeaders));

    if (is_array($postFields)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    } elseif (is_string($postFields)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    }

    curl_setopt($ch, CURLOPT_HEADERFUNCTION, "_xfac_api_curlHeaderFunction");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    foreach ($curlOptions as $option => $value) {
        curl_setopt($ch, $option, $value);
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode === 0) {
        $body = curl_error($ch);
    }

    curl_close($ch);

    $result = array(
        'http_code' => $httpCode,
        'headers' => $GLOBALS['_xfac_api_curlHeaders'],
        'body' => $body,
    );

    $headerContentType = _xfac_api_getHeader($result, 'Content-Type');
    if (strpos(implode('', $headerContentType), 'json') !== false) {
        $result['parts'] = json_decode($body, true);
    } else {
        $result['parts'] = array();
    }

    if (WP_DEBUG
        || $httpCode < 200 || $httpCode > 299
        || empty($result['parts']) || !empty($result['parts']['error'])
    ) {
        xfac_log('_xfac_api_curl %s (%s, %s) -> %s', $method, $url, var_export($postFields, true), $httpCode);
        foreach ($result['headers'] as $headerLine) {
            xfac_log('               | %s', $headerLine);
        }
        xfac_log('               | %s', $body);
    }

    return $result;
}

function _xfac_api_getHeader($curl, $headerName)
{
    $headerValues = array();

    if (!empty($curl['headers'])) {
        foreach ($curl['headers'] as $headerLine) {
            if (preg_match('/^' . preg_quote($headerName, '/') . ': (?<value>.+)$/', $headerLine, $matches)) {
                $headerValues[] = $matches['value'];
            }
        }
    }

    return $headerValues;
}

function _xfac_api_getHeaderLinkHub($curl)
{
    $headerLinks = _xfac_api_getHeader($curl, 'Link');
    $headerLinkHub = null;
    foreach ($headerLinks as $headerLink) {
        if (preg_match('/<(?<url>[^>]+)>; rel=hub/', $headerLink, $matches)) {
            return $matches['url'];
        }
    }

    return null;
}

function _xfac_api_getFailedResponse($curl)
{
    if (isset($curl['parts']['errors'])) {
        $GLOBALS['_xfac_api_lastErrors'] = $curl['parts']['errors'];
    } elseif (isset($curl['parts']['error'])) {
        $GLOBALS['_xfac_api_lastErrors'] = array($curl['parts']['error']);
    } else {
        $GLOBALS['_xfac_api_lastErrors'] = $curl;
    }

    return false;
}

function _xfac_api_prepareAccessTokenBody($body)
{
    $parts = @json_decode($body, true);

    if (!empty($parts['access_token'])) {
        if (!empty($parts['expires_in'])) {
            $parts['expire_date'] = time() + $parts['expires_in'];
            unset($parts['expires_in']);
        }

        if (!empty($parts['refresh_token_expires_in'])) {
            $parts['refresh_token_expire_date'] = time() + $parts['refresh_token_expires_in'];
            unset($parts['refresh_token_expires_in']);
        }

        foreach (array_keys($parts) as $key) {
            if (is_array($parts[$key])) {
                unset($parts[$key]);
            }
        }

        return $parts;
    } else {
        return _xfac_api_getFailedResponse($parts);
    }
}

function _xfac_api_encrypt($config, $array, $arrayKey, $data)
{
    if (!function_exists('mcrypt_encrypt')) {
        $array[$arrayKey] = $data;
        return $array;
    }

    $encryptKey = $config['clientSecret'];
    $encryptKey = md5($encryptKey, true);
    $padding = 16 - (strlen($data) % 16);
    $data .= str_repeat(chr($padding), $padding);

    $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $encryptKey, $data, MCRYPT_MODE_ECB);
    $encrypted = base64_encode($encrypted);

    $array[$arrayKey] = $encrypted;
    $array[sprintf('%s_algo', $arrayKey)] = 'aes128';
    return $array;
}

function xfac_api_getRedirectTo()
{
    if (!empty($_REQUEST['redirect_to'])) {
        $redirectTo = $_REQUEST['redirect_to'];

        $parsed = parse_url($redirectTo);
        if (empty($parsed['host'])) {
            // not a fully qualified url, we have to append home url
            $redirectTo = path_join(home_url(), ltrim($redirectTo, '/'));
        }

        return $redirectTo;
    }

    $redirectTo = 'http';
    if (isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS'] == 'on')) {
        $redirectTo .= 's';
    }
    $redirectTo .= '://';
    if ($_SERVER['SERVER_PORT'] != '80') {
        $redirectTo .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
    } else {
        $redirectTo .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    if (strpos($redirectTo, 'wp-login.php') !== false) {
        $redirectTo = home_url();
    }

    return $redirectTo;
}
