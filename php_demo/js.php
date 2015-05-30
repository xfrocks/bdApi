<?php

require_once('functions.php');

$config = loadConfiguration();
if (empty($config['api_root'])) {
    displaySetup();
}

$sdkUrl = generateJsSdkUrl($config['api_root']);

$implicitAuthorizeUrl = sprintf(
    '%s/index.php?oauth/authorize&response_type=token&client_id=%s&scope=%s&redirect_uri=%s',
    $config['api_root'],
    rawurlencode($config['api_key']),
    rawurlencode($config['api_scope']),
    rawurlencode(getCallbackUrl())
);

$isCallback = false;
if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'callback') {
    $isCallback = true;
}

?>

<?php require('html/header.php'); ?>

    <h3>JavaScript SDK</h3>
    <p>
        The API system supports a simple JavaScript SDK which can be included into any web page and
        perform callback to the API server. In this demo, click the button below to issue a
        <span class="code">.isAuthorized()</span> check to see whether user is logged in
        <strong>and</strong> has granted the specified scope.
    </p>

    <p>
        The code looks something like this:<br />

        <div class="code">&lt;<span class="pl-ent">script</span> <span class="pl-e">src</span>=<span class="pl-s1"><span class="pl-pds">"</span>js/jquery-1.11.2.min.js<span class="pl-pds">"</span></span>&gt;&lt;/<span class="pl-ent">script</span>&gt;</div>
        <div class="code">&lt;<span class="pl-ent">script</span> <span class="pl-e">src</span>=<span class="pl-s1"><span class="pl-pds">"</span><a href="<?php echo $sdkUrl; ?>" target="_blank"><?php echo $sdkUrl; ?></a><span class="pl-pds">"</span></span>&gt;&lt;/<span class="pl-ent">script</span>&gt;</div>
        <div class="code"><span class="pl-s2">&lt;<span class="pl-ent">script</span>&gt;</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;<span class="pl-s">var</span> api <span class="pl-k">=</span> <span class="pl-s3">window</span>.SDK;</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;api.init({ <span class="pl-s1"><span class="pl-pds">'</span>client_id<span class="pl-pds">'</span></span><span class="pl-k">:</span> <span class="pl-s1"><span class="pl-pds">'</span><?php echo $config['api_key']; ?><span class="pl-pds">'</span></span> });</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;api.isAuthorized(<span class="pl-s1"><span class="pl-pds">'</span><span id="scope_js"><?php echo $config['api_scope']; ?></span><span class="pl-pds">'</span></span>, <span class="pl-st">function</span> (<span class="pl-vpf">isAuthorized</span>, <span class="pl-vpf">apiData</span>) {</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;&nbsp;&nbsp;<span class="pl-k">if</span> (isAuthorized) {</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="pl-s3">alert</span>(<span class="pl-s1"><span class="pl-pds">'</span>Hi <span class="pl-pds">'</span></span> <span class="pl-k">+</span> apiData.username);</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;&nbsp;&nbsp;} <span class="pl-k">else</span> {</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="pl-s3">alert</span>(<span class="pl-s1"><span class="pl-pds">'</span>isAuthorized = false<span class="pl-pds">'</span></span>);</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;&nbsp;&nbsp;}</span></div>
        <div class="code"><span class="pl-s2">&nbsp;&nbsp;}</span></div>
        <div class="code"><span class="pl-s2">&lt;/<span class="pl-ent">script</span>&gt;</span></div>
    </p>
    <script src="js/jquery-1.11.2.min.js"></script>
    <script src="<?php echo $sdkUrl; ?>"></script>
    <script>
        if (typeof window.SDK != 'undefined') {
            var api = window.SDK;
            api.init({
                'client_id': '<?php echo $config['api_key']; ?>'
            });

            $(document).ready(function () {
                var $jsForm = $('#form_js');
                var $scope = $('#scope_ctrl');
                var $jsScope = $('#scope_js');

                $jsForm.submit(function (e) {
                    e.preventDefault();

                    var scope = $scope.val();

                    api.isAuthorized(scope, function (isAuthorized, apiData) {
                        if (isAuthorized) {
                            alert('Hi ' + apiData.username);
                        } else {
                            alert('isAuthorized = false');
                        }
                    })
                });

                $scope.keyup(function() {
                    $jsScope.text($scope.val().trim());
                })
            });
        }
    </script>

    <hr/>

    <form id="form_js">
        <label for="scope_ctrl">Scope</label><br/>
        <input id="scope_ctrl" type="text" name="scope" value="<?php echo $config['api_scope']; ?>"/><br/>
        <br/>

        <input type="submit" value=".isAuthorized()"/>
    </form>

    <hr />

    <h3>Implicit Grant Type</h3>
    <p>
        Other than the traditional grant types, implicit allows more creative usages of the API system.
        <a href="http://tools.ietf.org/html/rfc6749#section-4.2" target="_blank">Read more about it here</a>.
    </p>

    <?php if ($isCallback): ?>
        <div class="implicit">
            <h4>Token found from window.location.hash!</h4>
            <?php require('html/form_request.php'); ?>
        </div>

        <script>
            if (window.location.hash.length > 0) {
                // just a quick (and dirty) way to extract the access token
                var matches = window.location.hash.match(/access_token=([^&]+)&/);
                if (matches !== null) {
                    var accessToken = matches[1];

                    $('.implicit').show();
                    $('#access_token_ctrl').val(accessToken);
                }
            }
        </script>
    <?php endif; ?>

    <p>
        <a href="<?php echo $implicitAuthorizeUrl; ?>">Click here</a>
        <?php if ($isCallback): ?>
            to refresh the token.
        <?php else: ?>
            to start the authorizing flow.
        <?php endif; ?>
    </p>

<?php require('html/footer.php'); ?>