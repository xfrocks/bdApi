<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

?>

<style>
    #xfacDashboardOptions fieldset label {
        margin-top: 1em !important;
        margin-bottom: 0 !important;
    }
</style>
<div class="wrap">
    <div id="icon-options-general" class="icon32">
        <br/>
    </div>
    <h2><?php _e('XenForo Guest Account', 'xenforo-api-consumer'); ?></h2>

    <form method="post" action="options-general.php" id="xfacDashboardOptions">
        <input type="hidden" name="page" value="xfac"/>
        <input type="hidden" name="do" value="xfac_xf_guest_account_submit"/>

        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label
                        for="xfac_guest_username"><?php _e('Username', 'xenforo-api-consumer'); ?></label></th>
                <td>
                    <input name="xfac_guest_username" type="text" id="xfac_guest_username" class="regular-text"/>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label
                        for="xfac_guest_password"><?php _e('Password', 'xenforo-api-consumer'); ?></label></th>
                <td>
                    <input name="xfac_guest_password" type="password" id="xfac_guest_password" class="regular-text"/>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary"
                   value="<?php _e('Save Changes'); ?>"/>
        </p>
    </form>

</div>