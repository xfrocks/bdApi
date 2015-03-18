<?php
/*
	This is a demo for [bd] API add-on for XenForo.
	It includes a simple OAuth 2 authorization flow which has 3 actors (XenForo, User and Demo App):
		1. Demo App sends User to an authorization page on XenForo website
		2. User verifies the information and grant access
		3. XenForo sends User back to a callback page on Demo App website
		4. Demo App obtains an access token from XenForo server
		5. Demo App can now make request on behalf of User
*/

require_once('functions.php');

$config = loadConfiguration();
if (empty($config['api_root'])) {
    displaySetup();
}

$message = '';
$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
$accessToken = !empty($_REQUEST['access_token']) ? $_REQUEST['access_token'] : '';

switch ($action) {
    case 'callback':
        // step 3
        if (empty($_REQUEST['code'])) {
            die('Callback request must have `code` query parameter!');
        }

        $tokenUrl = sprintf(
            '%s/index.php?oauth/token',
            $config['api_root']
        );

        $postFields = array(
            'grant_type' => 'authorization_code',
            'client_id' => $config['api_key'],
            'client_secret' => $config['api_secret'],
            'code' => $_REQUEST['code'],
            'redirect_uri' => getCallbackUrl(),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // step 4
        $body = curl_exec($ch);
        curl_close($ch);

        $json = @json_decode($body, true);
        if (empty($json)) {
            die('Unexpected response from server: ' . $body);
        }

        if (!empty($json['access_token'])) {
            $accessToken = $json['access_token'];
            $message = sprintf(
                'Obtained access token successfully!<br />Scopes: %s<br />Expires At: %s',
                $json['scope'],
                date('c', time() + $json['expires_in'])
            );
        } else {
            $message = renderMessageForJson($tokenUrl, $json);
        }
        break;
    case 'request':
        // step 5
        if (!empty($accessToken) && !empty($_REQUEST['url'])) {
            $url = $_REQUEST['url'];
            if (strpos($url, $config['api_root']) === false) {
                $url = sprintf(
                    '%s/index.php?%s&oauth_token=%s',
                    $config['api_root'],
                    $_REQUEST['url'],
                    rawurlencode($accessToken)
                );
            }

            $body = file_get_contents($url);
            $json = @json_decode($body, true);
            if (empty($json)) {
                die('Unexpected response from server: ' . $body);
            }

            $message = renderMessageForJson($url, $json);
        }
        break;
    case 'authorize':
    default:
        // step 1
        $authorizeUrl = sprintf(
            '%s/index.php?oauth/authorize&response_type=code&client_id=%s&redirect_uri=%s',
            $config['api_root'],
            rawurlencode($config['api_key']),
            rawurlencode(getCallbackUrl())
        );

        $message = sprintf(
            '<a href="%s">Click here</a> to go to %s and start the authorization.',
            $authorizeUrl,
            parse_url($authorizeUrl, PHP_URL_HOST)
        );
        break;
}

?>

<?php require('html/header.php'); ?>

<?php if (!empty($message)): ?>
    <div class="message"><?php echo $message; ?></div>
    <hr/>
<?php endif; ?>

<?php if (!empty($accessToken)): ?>
    <h3>Test Sending Request</h3>
    <form action="index.php?action=request" method="POST">
        <label for="access_token_ctrl">Access Token</label><br/>
        <input id="access_token_ctrl" type="text" name="access_token" value="<?php echo $accessToken; ?>"/><br/>
        <br/>

        <select name="url">
            <option value="users/me">Get detailed information of authorized user (GET /users/me)</option>
            <option value="navigation">Get list of navigation elements (GET /navigation)</option>
        </select><br/>
        <br/>

        <input type="submit" value="Make API Request"/>
    </form>
    <hr/>
<?php endif; ?>

    <h3>Test JavaScript</h3>
    <p>Click <a href="js.php">here</a></p>

<?php require('html/footer.php'); ?>