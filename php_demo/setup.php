<?php

require_once('functions.php');

// load from request
$apiRoot = !empty($_REQUEST['api_root']) ? $_REQUEST['api_root'] : '';
$apiKey = !empty($_REQUEST['api_key']) ? $_REQUEST['api_key'] : '';
$apiSecret = !empty($_REQUEST['api_secret']) ? $_REQUEST['api_secret'] : '';
$apiScope = !empty($_REQUEST['api_scope']) ? $_REQUEST['api_scope'] : '';

// fallback to config if needed
if (empty($apiRoot) && empty($apiKey) && empty($apiSecret) && empty($apiScope)) {
    $config = loadConfiguration();
    $apiRoot = $config['api_root'];
    $apiKey = $config['api_key'];
    $apiSecret = $config['api_secret'];
    $apiScope = $config['api_scope'];
}

// clean up
$apiRoot = rtrim($apiRoot, '/');
if (empty($apiScope)) {
    $apiScope = 'read';
}

// save configuration to session
if ((empty($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET')
    && !empty($apiRoot) && !empty($apiKey) && !empty($apiSecret) && !empty($apiScope)
) {
    $_SESSION['api_root'] = $apiRoot;
    $_SESSION['api_key'] = $apiKey;
    $_SESSION['api_secret'] = $apiSecret;
    $_SESSION['api_scope'] = $apiScope;
    $_SESSION['ignore_config'] = true;

    $location = 'index.php';

    if (!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
        // a HEAD request, redirect to setup.php with all configuration needed
        // but only after verification (!important)
        // used in the add-on installer when it issues HEAD request to verify itself
        if (isLocal($apiRoot)) {
            // accepts all local addresses
        } else {
            $ott = generateOneTimeToken($apiKey, $apiSecret);
            list(, $json) = makeRequest('', $apiRoot, $ott);
            if (empty($json['links'])) {
                header('HTTP/1.0 403 Forbidden');
                exit;
            }
        }

        $location = sprintf(
            '%s?api_root=%s&api_key=%s&api_secret=%s&api_scope=%s',
            getBaseUrl(),
            rawurlencode($apiRoot),
            rawurlencode($apiKey),
            rawurlencode($apiSecret),
            rawurlencode($apiScope)
        );

        $bitlyToken = getenv('BITLY_TOKEN');
        if (!empty($bitlyToken)) {
            $location = bitlyShorten($bitlyToken, $location);
        }
    }

    header('Location: ' . $location);
    exit;
}

?>

<?php require('html/header.php'); ?>

    <div>
        <p>There are two ways to configure this demo:</p>
        <ul>
            <li>
                You can either enter all the information in the form below and they will be saved temporary
                for your current browser session.
            </li>
            <li>
                Or you can create a new <span class="code">config.php</span> using the template from
                <span class="code">config.php.template</span> for permanent usage.
            </li>
        </ul>
    </div>
    <hr/>

    <form action="setup.php" method="POST">
        <label for="api_root_ctrl">API Root</label><br/>
        <input id="api_root_ctrl" type="text" name="api_root" value="<?php echo $apiRoot; ?>"
               placeholder="<?php echo $config['placeholder']['api_root']; ?>"/><br/>
        <br/>

        <label for="api_key_ctrl">API Key</label><br/>
        <input id="api_key_ctrl" type="text" name="api_key" value="<?php echo $apiKey; ?>"
               placeholder="<?php echo $config['placeholder']['api_key']; ?>"/><br/>
        <br/>

        <label for="api_secret_ctrl">API Secret</label><br/>
        <input id="api_secret_ctrl" type="text" name="api_secret" value="<?php echo $apiSecret; ?>"
               placeholder="<?php echo $config['placeholder']['api_secret']; ?>"/><br/>
        <br/>

        <label for="api_scope_ctrl">API Scope (separated by space)</label><br/>
        <input id="api_scope_ctrl" type="text" name="api_scope" value="<?php echo $apiScope; ?>"
               placeholder="<?php echo $config['placeholder']['api_scope']; ?>"/><br/>
        <br/>

        <input type="submit" value="Save"/>
    </form>

<?php require('html/footer.php'); ?>