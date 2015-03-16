<?php
/*
	This is a demo for [bd] API add-on for XenForo.
	It includes a simple OAuth 2 authorization flow which has 3 actors (XenForo, User and Demo App):
		1. Demo App sends User to an authorization page on XenForo website
		2. User verifies the information and grant access
		3. XenForo sends User back to a callback page on Demo App website
		4. Demo App obtains an access token from XenForo server
		5. Demo App can now make request on behalf of User

	Because this is a demo, it allows user to enter the API information (root url, key, secret, etc.),
	this rarely happens in real life. The App adminitrator often configures everything in his/her
	backend system. In those situations, the callback url can be the same and does not require recalculation.
	It is also worth noting that an access token may expire and the App should be able to refresh as needed.
	Consult OAuth 2 documentation for more information.
*/

$action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : '';
$apiRoot = rtrim(!empty($_REQUEST['api_root']) ? $_REQUEST['api_root'] : '', '/');
$apiKey = !empty($_REQUEST['api_key']) ? $_REQUEST['api_key'] : '';
$apiSecret = !empty($_REQUEST['api_secret']) ? $_REQUEST['api_secret'] : '';
$apiScope = !empty($_REQUEST['api_scope']) ? $_REQUEST['api_scope'] : 'read';
$accessToken = !empty($_REQUEST['access_token']) ? $_REQUEST['access_token'] : '';;

$message = '';
require_once('functions.php');

if (!empty($apiRoot) && !empty($apiKey) && !empty($apiSecret) && !empty($apiScope)) {
	switch($action) {
		case 'authorize':
			$authorizeUrl = sprintf(
				'%s/index.php?oauth/authorize&response_type=code&client_id=%s&redirect_uri=%s',
				$apiRoot,
				rawurlencode($apiKey),
				rawurlencode(getCallbackUrl())
			);
			
			$message = sprintf(
				'<a href="%s">Click here</a> to go to %s and start the authorization.',
				$authorizeUrl,
				parse_url($authorizeUrl, PHP_URL_HOST)
			);
			break;
		case 'callback':
			if (empty($_REQUEST['code'])) {
				die('Callback request must have `code` query parameter!');
			}

			$tokenUrl = sprintf(
				'%s/index.php?oauth/token',
				$apiRoot
			);

			$postFields = array(
				'grant_type' => 'authorization_code',
				'client_id' => $apiKey,
				'client_secret' => $apiSecret,
				'code' => $_REQUEST['code'],
				'redirect_uri' => getCallbackUrl(),
			);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $tokenUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
			if (!empty($accessToken) && !empty($_REQUEST['url'])) {
				$url = $_REQUEST['url'];
				if (strpos($url, $apiRoot) === false) {
					$url = sprintf(
						'%s/index.php?%s&oauth_token=%s',
						$apiRoot,
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
	}
}

?>
<!doctype html>
<html lang=en>
<head>
	<meta charset=utf-8>
	<title>[bd] API - PHP Demo</title>
	<style>
		a {
			color: blue;
		}
		input {
			margin: 5px;
			max-width: 90%;
			width: 400px;
		}
	</style>
</head>
<body>
	<p><?php echo $message; ?></p>

	<?php if (empty($accessToken)): ?>
	<form action="index.php" method="GET">
		<input type="text" name="api_root" value="<?php echo $apiRoot; ?>"
			placeholder="http://domain.com/xenforo/api" /><br />

		<input type="text" name="api_key" value="<?php echo $apiKey; ?>"
			placeholder="API key goes here" /><br />

		<input type="text" name="api_secret" value="<?php echo $apiSecret; ?>"
			placeholder="API secret goes here" /><br />

		<input type="text" name="api_scope" value="<?php echo $apiScope; ?>"
			placeholder="API scopes go here (read)" /><br />

		<input type="hidden" name="action" value="authorize" />
		<input type="submit" value="Authorize API" />
	</form>
	<?php else: ?>
	<form action="index.php" method="GET">
		<input type="text" name="access_token" value="<?php echo $accessToken; ?>" /><br />
		<select name="url">
			<option value="users/me">Get detailed information of authorized user (GET /users/me)</option>
			<option value="navigation">Get list of navigation elements (GET /navigation)</option>
		</select><br />

		<input type="hidden" name="api_root" value="<?php echo $apiRoot; ?>" />
		<input type="hidden" name="api_key" value="<?php echo $apiKey; ?>" />
		<input type="hidden" name="api_secret" value="<?php echo $apiSecret; ?>" />
		<input type="hidden" name="api_scope" value="<?php echo $apiScope; ?>" />

		<input type="hidden" name="action" value="request" />
		<input type="submit" value="Make API Request" />
	</form>
	<?php endif; ?>
</body>
</html>