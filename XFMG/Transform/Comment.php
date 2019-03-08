<?php
/**
 * Created by PhpStorm.
 * User: datbth
 * Date: 09/12/2018
 * Time: 10:59
 */

namespace Xfrocks\Api\XFMG\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class Comment extends AbstractHandler
{
    const KEY_ID = 'comment_id';
    const KEY_CONTENT_ID = 'content_id';
    const KEY_CONTENT_TYPE = 'content_type';
    const KEY_BODY = 'comment_body';
    const KEY_USER_ID = 'user_id';
    const KEY_USERNAME = 'username';
    const KEY_COMMENT_DATE = 'comment_date';
    const KEY_LAST_EDIT_DATE = 'last_edit_date';
    const KEY_LIKE_COUNT = 'like_count';

    const DYNAMIC_KEY_BODY_HTML = 'body_html';
    const DYNAMIC_KEY_BODY_PLAIN = 'body_plain_text';
    const DYNAMIC_KEY_IS_LIKED = 'is_liked';
    const DYNAMIC_KEY_IS_DELETED = 'is_deleted';
    const DYNAMIC_KEY_IS_IGNORED = 'is_ignored';

    const LINK_CONTENT = 'content';

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XFMG\Entity\Comment $comment */
        $comment = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_BODY_HTML:
                return $this->renderBbCodeHtml($key, $comment->message, $comment);
            case self::DYNAMIC_KEY_BODY_PLAIN:
                return $this->renderBbCodePlainText($comment->message);
            case self::DYNAMIC_KEY_IS_DELETED:
                return $comment->comment_state === 'deleted';
            case self::DYNAMIC_KEY_IS_IGNORED:
                if (!\XF::visitor()->user_id) {
                    return false;
                }

                return $comment->isIgnored();
            case self::DYNAMIC_KEY_IS_LIKED:
                return $comment->isLiked();
        }

        return null;
    }

    public function collectPermissions($context)
    {
        /** @var \XFMG\Entity\Comment $comment */
        $comment = $context->getSource();

        $permissions = [
            self::PERM_DELETE => $comment->canDelete(),
            self::PERM_EDIT => $comment->canEdit(),
            self::PERM_LIKE => $comment->canLike(),
        ];

        return $permissions;
    }

    public function collectLinks($context)
    {
        /** @var \XFMG\Entity\Comment $comment */
        $comment = $context->getSource();

        $links = [
            self::LINK_DETAIL => $this->buildApiLink('media/comments', $comment),
            self::LINK_LIKES => $this->buildApiLink('media/comments/likes', $comment),
        ];

        return $links;
    }

    public function getMappings($context)
    {
        return [
            'comment_id' => self::KEY_ID,
            'content_id' => self::KEY_CONTENT_ID,
            'content_type' => self::KEY_CONTENT_TYPE,
            'message' => self::KEY_BODY,
            'comment_date' => self::KEY_COMMENT_DATE,
            'last_edit_date' => self::KEY_LAST_EDIT_DATE,
            'user_id' => self::KEY_USER_ID,
            'username' => self::KEY_USERNAME,
            'likes' => self::KEY_LIKE_COUNT,

            self::DYNAMIC_KEY_BODY_HTML,
            self::DYNAMIC_KEY_BODY_PLAIN,
            self::DYNAMIC_KEY_IS_DELETED,
            self::DYNAMIC_KEY_IS_LIKED,
        ];
    }
}
