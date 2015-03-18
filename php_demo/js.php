<?php

require_once('functions.php');

$config = loadConfiguration();
if (empty($config['api_root'])) {
    displaySetup();
}

$sdkUrl = generateJsSdkUrl($config['api_root']);

?>

<?php require('html/header.php'); ?>

    <h3>Test JavaScript</h3>
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

<?php require('html/footer.php'); ?>