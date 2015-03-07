<div>
    <ul>
        <?php $existingForumIds = array(); ?>

        <?php foreach ($records as $record): ?>
            <li>
                <?php if (!empty($record->syncData['direction']) AND $record->syncData['direction'] == 'push'): ?>
                    <?php _e('Pushed', 'xenforo-api-consumer'); ?>
                <?php elseif (!empty($record->syncData['direction']) AND in_array($record->syncData['direction'], array('pull', 'subscription'), true)): ?>
                    <?php _e('Pulled', 'xenforo-api-consumer'); ?>
                <?php else: ?>
                    <?php _e('Synchronized', 'xenforo-api-consumer'); ?>
                <?php endif; ?>

                <ul style="margin-left: 20px;">
                    <li>
                        <?php _e('Thread:', 'xenforo-api-consumer'); ?>
                        <a href="<?php echo $record->syncData['thread']['links']['permalink']; ?>" target="_blank">
                            <?php echo $record->syncData['thread']['thread_title']; ?>
                        </a>
                    </li>

                    <li>
                        <?php _e('Forum:', 'xenforo-api-consumer'); ?>
                        <?php if (!empty($meta['forums'])): ?>
                            <?php foreach ($meta['forums'] as $forum): ?>
                                <?php if ($forum['forum_id'] == $record->syncData['thread']['forum_id']): ?>
                                    <?php $existingForumIds[] = $forum['forum_id']; ?>
                                    <a href="<?php echo $forum['links']['permalink'] ?>" target="_blank">
                                        <?php echo $forum['forum_title']; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </li>

                    <li>
                        <?php _e('Updated at', 'xenforo-api-consumer'); ?>
                        <?php echo date_i18n(__('M j, Y @ G:i'), $record->sync_date); ?>
                    </li>

                    <?php if (!empty($record->syncData['subscribed'])): ?>
                        <li><?php _e('Subscribed for future posts', 'xenforo-api-consumer'); ?></li>
                    <?php endif; ?>

                    <li>
                        <label>
                            <input type="checkbox" name="xfac_delete_sync[]"
                                   value="<?php echo $record->provider_content_id; ?>"/>
                            <?php _e('Stop Synchronization', 'xenforo-api-consumer'); ?>
                        </label>
                    </li>
                </ul>
            </li>
        <?php endforeach; ?>

        <?php if (!empty($meta['forums'])): ?>
            <li>
                <?php _e('Push to forum:', 'xenforo-api-consumer'); ?>

                <select name="xfac_forum_id">
                    <option value="0"></option>
                    <?php foreach ($meta['forums'] as $forum): ?>
                        <?php $disabled = in_array($forum['forum_id'], $existingForumIds); ?>
                        <option
                            value="<?php echo $forum['forum_id']; ?>"<?php if ($disabled) echo ' disabled="disabled"'; ?>>
                            <?php echo $forum['forum_title']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </li>
        <?php endif; ?>
    </ul>
</div>