<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

?>

<table class="form-table">
    <tbody>
    <tr>
        <th>
            <label>
                <?php _e('Connected Account', 'xenforo-api-consumer'); ?>
            </label>
        </th>
        <td>
            <ul>
                <?php if (!empty($apiRecords)): ?>
                    <?php foreach ($apiRecords as $apiRecord): ?>
                        <li>
                            <label for="xfac_disconnect_<?php echo $apiRecord->id; ?>">
                                <input type="checkbox" name="xfac_disconnect[<?php echo $apiRecord->id; ?>]"
                                       id="xfac_disconnect_<?php echo $apiRecord->id; ?>" value="1">
                                <?php _e('Check to disconnect from', 'xenforo-api-consumer'); ?>
                                <a href="<?php echo $apiRecord->profile['links']['permalink']; ?>"
                                   target="_blank"><?php echo $apiRecord->profile['username']; ?></a>
                            </label>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <?php if (!empty($xfUsers)): ?>
                            <label for="xfac_connect_0">
                                <input type="radio" name="xfac_connect" id="xfac_connect_0" value="0" checked="checked">
                                <?php _e('None', 'xenforo-api-consumer'); ?>
                            </label>
                        <?php else: ?>
                            <?php _e('None', 'xenforo-api-consumer'); ?>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>

                <?php if (!empty($xfUsers)): ?>
                    <?php foreach ($xfUsers as $xfUser): ?>
                        <li>
                            <label for="xfac_connect_<?php echo $xfUser['user_id']; ?>">
                                <input type="radio" name="xfac_connect" id="xfac_connect_<?php echo $xfUser['user_id']; ?>"
                                       value="<?php echo $xfUser['user_id']; ?>">
                                <?php _e('Connect to', 'xenforo-api-consumer'); ?>
                                <a href="<?php echo $xfUser['links']['permalink']; ?>"
                                   target="_blank"><?php echo $xfUser['username']; ?></a>
                            </label>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </td>
    </tr>
    </tbody>
</table>