<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class ConversationRecipient extends AbstractHandler
{
    const KEY_USER_ID = 'user_id';

    const DYNAMIC_KEY_USERNAME = 'username';
    const DYNAMIC_KEY_AVATAR = 'avatar';
    const DYNAMIC_KEY_AVATAR_BIG = 'avatar_big';
    const DYNAMIC_KEY_AVATAR_SMALL = 'avatar_small';

    public function canView(TransformContext $context)
    {
        return true;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            'user_id' => self::KEY_USER_ID,

            self::DYNAMIC_KEY_USERNAME,
            self::DYNAMIC_KEY_AVATAR,
            self::DYNAMIC_KEY_AVATAR_BIG,
            self::DYNAMIC_KEY_AVATAR_SMALL
        ];
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XF\Entity\ConversationRecipient $recipient */
        $recipient = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_USERNAME:
                /** @var \XF\Entity\User|null $user */
                $user = $recipient->User;
                if (!$user) {
                    return \XF::phrase('deleted_member');
                }

                return $user->username;
            case self::DYNAMIC_KEY_AVATAR:
                return $this->collectUserAvatarUrl($recipient, 'l');
            case self::DYNAMIC_KEY_AVATAR_BIG:
                return $this->collectUserAvatarUrl($recipient, 'o');
            case self::DYNAMIC_KEY_AVATAR_SMALL:
                return $this->collectUserAvatarUrl($recipient, 's');
        }

        return null;
    }

    protected function collectUserAvatarUrl(\XF\Entity\ConversationRecipient $recipient, $sizeCode)
    {
        /** @var \XF\Entity\User|null $user */
        $user = $recipient->User;

        if (!$user) {
            return null;
        }

        return $user->getAvatarUrl($sizeCode);
    }
}
