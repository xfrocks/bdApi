<?php

require_once('functions.php');
require_once('jwt_functions.php');

$config = loadConfiguration();
if (empty($config['api_root'])) {
    displaySetup();
}

if (!empty($_REQUEST['action'])
	&& $_REQUEST['action'] == 'obtain'
	&& !empty($_REQUEST['private_key'])
) {
	$assertion = generateJwtAssertion(
		$_REQUEST['private_key'],
		$config['api_key'],
		(!empty($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0),
		$config['api_root']
	);

	$url = sprintf('%s/index.php?oauth/token', $config['api_root']);
	$json = makeCurlPost($url, array(
		'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
		'assertion' => $assertion,
	));

	$message = renderMessageForJson('obtain', $json);

	if (!empty($json['access_token'])) {
		$accessToken = $json['access_token'];
	}
}

?>

<?php require('html/header.php'); ?>

    <h3>JWT Bearer Grant Type</h3>

	<?php if (!empty($message)): ?>
    	<div class="message"><?php echo $message; ?></div>

    	<?php if (!empty($accessToken)): ?>
    		<?php require('html/form_request.php'); ?>
    	<?php endif; ?>
    <?php else: ?>
    	<p>
	        This grant type is used when the client wants to receive access tokens without transmitting sensitive information
	        such as the client secret. Please note that you must have a valid public / private key pair <strong>and</strong>
	        you have configured the client with the public key.
	        <a href="http://tools.ietf.org/html/draft-ietf-oauth-jwt-bearer-07#section-1" target="_blank">Read more about it here</a>.<br />
	        <br />
	        To test this grant type, enter the private key in the textarea below and click "Obtain Token".
	    </p>

	    <form id="form_jwt" action="jwt.php?action=obtain" method="POST">
		    <label for="private_key_ctrl">Private Key</label><br/>
		    <textarea id="private_key_ctrl" name="private_key" rows="10" style="width: 100%"></textarea>

		    <input type="hidden" name="user_id" value="0" />
		    <input type="submit" value="Obtain Token" />
	    </form>
	<?php endif; ?>

<?php require('html/footer.php'); ?>