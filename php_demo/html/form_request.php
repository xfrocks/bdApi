<form id="form_request" action="index.php?action=request" method="POST">
    <label for="access_token_ctrl">Access Token</label><br/>
    <input id="access_token_ctrl" type="text" name="access_token" value="<?php if (!empty($accessToken)) echo $accessToken; ?>"/><br/>
    <br/>

    <select name="url">
        <option value="users/me">Get detailed information of authorized user (GET /users/me)</option>
        <option value="navigation">Get list of navigation elements (GET /navigation)</option>
    </select><br/>
    <br/>

    <input type="submit" value="Make API Request"/>
</form>