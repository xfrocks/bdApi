<?php

namespace Xfrocks\Api\XF\Transform;

use XF\Entity\ConversationMaster;
use XF\Entity\UserProfile;
use XF\Repository\UserGroup;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transform\AbstractHandler;

class User extends AbstractHandler
{
    const KEY_ID = 'user_id';
    const KEY_LIKE_COUNT = 'user_like_count';
    const KEY_MESSAGE_COUNT = 'user_message_count';
    const KEY_NAME = 'username';
    const KEY_REGISTER_DATE = 'user_register_date';

    const DYNAMIC_KEY_DOB_DAY = 'user_dob_day';
    const DYNAMIC_KEY_DOB_MONTH = 'user_dob_month';
    const DYNAMIC_KEY_DOB_YEAR = 'user_dob_year';
    const DYNAMIC_KEY_EMAIL = 'user_email';
    const DYNAMIC_KEY_EXTERNAL_AUTHS = 'user_external_authentications';
    const DYNAMIC_KEY_FIELDS = 'fields';
    const DYNAMIC_KEY_GROUPS = 'user_groups';
    const DYNAMIC_KEY_GROUPS__IS_PRIMARY = 'is_primary_group';
    const DYNAMIC_KEY_HAS_PASSWORD = 'user_has_password';
    const DYNAMIC_KEY_IS_FOLLOWED = 'user_is_followed';
    const DYNAMIC_KEY_IS_IGNORED = 'user_is_ignored';
    const DYNAMIC_KEY_IS_VALID = 'user_is_valid';
    const DYNAMIC_KEY_IS_VERIFIED = 'user_is_verified';
    const DYNAMIC_KEY_IS_VISITOR = 'user_is_visitor';
    const DYNAMIC_KEY_LAST_SEEN_DATE = 'user_last_seen_date';
    const DYNAMIC_KEY_PERMISSIONS_EDIT = 'edit_permissions';
    const DYNAMIC_KEY_PERMISSIONS_SELF = 'self_permissions';
    const DYNAMIC_KEY_TIMEZONE_OFFSET = 'user_timezone_offset';
    const DYNAMIC_KEY_TITLE = 'user_title';
    const DYNAMIC_KEY_UNREAD_CONVO_COUNT = 'user_unread_conversation_count';
    const DYNAMIC_KEY_UNREAD_NOTIF_COUNT = 'user_unread_notification_count';

    const LINK_AVATAR = 'avatar';
    const LINK_AVATAR_BIG = 'avatar_big';
    const LINK_AVATAR_SMALL = 'avatar_small';
    const LINK_FOLLOWERS = 'followers';
    const LINK_FOLLOWINGS = 'followings';
    const LINK_IGNORE = 'ignore';
    const LINK_TIMELINE = 'timeline';

    const PERM_IGNORE = 'ignore';
    const PERM_FOLLOW = 'follow';
    const PERM_PROFILE_POST = 'profile_post';
    const PERM_SELF_CREATE_CONVO = 'create_conversation';
    const PERM_SELF_ATTACH_CONVO = 'upload_attachment_conversation';

    protected $flagFullAccess = false;

    public function calculateDynamicValue($key)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->source;
        $visitor = \XF::visitor();

        switch ($key) {
            case self::DYNAMIC_KEY_DOB_DAY:
                if (!$this->flagFullAccess) {
                    return null;
                }
                $userProfile = $user->Profile;
                if (empty($userProfile)) {
                    return null;
                }
                return $userProfile->dob_day;
            case self::DYNAMIC_KEY_DOB_MONTH:
                if (!$this->flagFullAccess) {
                    return null;
                }
                $userProfile = $user->Profile;
                if (empty($userProfile)) {
                    return null;
                }
                return $userProfile->dob_month;
            case self::DYNAMIC_KEY_DOB_YEAR:
                if (!$this->flagFullAccess) {
                    return null;
                }
                $userProfile = $user->Profile;
                if (empty($userProfile)) {
                    return null;
                }
                return $userProfile->dob_year;
            case self::DYNAMIC_KEY_EMAIL:
                if (!$this->flagFullAccess) {
                    return null;
                }
                return $user->email;
            case self::DYNAMIC_KEY_EXTERNAL_AUTHS:
                return $this->collectExternalAuths();
            case self::DYNAMIC_KEY_FIELDS:
                return $this->collectFields();
            case self::DYNAMIC_KEY_GROUPS:
                return $this->collectGroups($key);
            case self::DYNAMIC_KEY_HAS_PASSWORD:
                if (!$this->flagFullAccess) {
                    return null;
                }
                $userAuth = $user->Auth;
                if (empty($userAuth)) {
                    return false;
                }
                return $userAuth->getAuthenticationHandler()->hasPassword();
            case self::DYNAMIC_KEY_IS_FOLLOWED:
                if (!$this->flagFullAccess) {
                    return null;
                }
                return $visitor->isFollowing($user);
            case self::DYNAMIC_KEY_IS_IGNORED:
                return $visitor->isIgnoring($user->user_id);
            case self::DYNAMIC_KEY_IS_VALID:
                return (!$user->is_banned &&
                    in_array($user->user_state, ['valid', 'email_confirm', 'email_confirm_edit']));
            case self::DYNAMIC_KEY_IS_VERIFIED:
                return $user->user_state === 'valid';
            case self::DYNAMIC_KEY_IS_VISITOR:
                return $user->user_id === $visitor->user_id;
            case self::DYNAMIC_KEY_LAST_SEEN_DATE:
                return $user->canViewCurrentActivity() ? $user->last_activity : $user->register_date;
            case self::DYNAMIC_KEY_PERMISSIONS_EDIT:
                return $this->collectPermissionsEdit();
            case self::DYNAMIC_KEY_PERMISSIONS_SELF:
                return $this->collectPermissionsSelf();
            case self::DYNAMIC_KEY_TIMEZONE_OFFSET:
                if (!$this->flagFullAccess) {
                    return null;
                }
                $dtz = new \DateTimeZone($user->timezone);
                $dt = new \DateTime('now', $dtz);
                return $dtz->getOffset($dt);
            case self::DYNAMIC_KEY_TITLE:
                return strip_tags($this->getTemplater()->fn('user_title', [$user]));
            case self::DYNAMIC_KEY_UNREAD_CONVO_COUNT:
                if (!$this->flagFullAccess ||
                    !$this->checkSessionScope(Server::SCOPE_PARTICIPATE_IN_CONVERSATIONS)) {
                    return null;
                }
                return $user->conversations_unread;
            case self::DYNAMIC_KEY_UNREAD_NOTIF_COUNT:
                if (!$this->flagFullAccess) {
                    return null;
                }
                return $user->alerts_unread;
        }

        return null;
    }

    public function collectLinks()
    {
        /** @var \XF\Entity\User $user */
        $user = $this->source;

        $links = [
            self::LINK_AVATAR => $user->getAvatarUrl('l'),
            self::LINK_AVATAR_BIG => $user->getAvatarUrl('o'),
            self::LINK_AVATAR_SMALL => $user->getAvatarUrl('s'),
            self::LINK_DETAIL => $this->buildApiLink('users', $user),
            self::LINK_FOLLOWERS => $this->buildApiLink('users/followers', $user),
            self::LINK_FOLLOWINGS => $this->buildApiLink('users/followings', $user),
            self::LINK_IGNORE => $this->buildApiLink('users/ignore', $user),
            self::LINK_PERMALINK => $this->buildPublicLink('members', $user)
        ];

        if ($user->canViewPostsOnProfile()) {
            $links[self::LINK_TIMELINE] = $this->buildApiLink('users/timeline', $user);
        }

        return $links;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\User $user */
        $user = $this->source;
        $visitor = \XF::visitor();

        return [
            self::PERM_EDIT => $this->flagFullAccess,
            self::PERM_IGNORE => $visitor->canIgnoreUser($user),
            self::PERM_FOLLOW => $visitor->canFollowUser($user),
            self::PERM_PROFILE_POST => $user->canPostOnProfile()
        ];
    }

    public function getFetchWith(array $extraWith = [])
    {
        return array_merge([
            'Activity',
            'Auth',
            'Privacy',
            'Profile'
        ], $extraWith);
    }

    public function getMappings()
    {
        $mappings = [
            'like_count' => self::KEY_LIKE_COUNT,
            'message_count' => self::KEY_MESSAGE_COUNT,
            'register_date' => self::KEY_REGISTER_DATE,
            'user_id' => self::KEY_ID,
            'username' => self::KEY_NAME,

            self::DYNAMIC_KEY_DOB_DAY,
            self::DYNAMIC_KEY_DOB_MONTH,
            self::DYNAMIC_KEY_DOB_YEAR,
            self::DYNAMIC_KEY_EMAIL,
            self::DYNAMIC_KEY_EXTERNAL_AUTHS,
            self::DYNAMIC_KEY_FIELDS,
            self::DYNAMIC_KEY_GROUPS,
            self::DYNAMIC_KEY_HAS_PASSWORD,
            self::DYNAMIC_KEY_IS_FOLLOWED,
            self::DYNAMIC_KEY_IS_IGNORED,
            self::DYNAMIC_KEY_IS_VALID,
            self::DYNAMIC_KEY_IS_VERIFIED,
            self::DYNAMIC_KEY_IS_VISITOR,
            self::DYNAMIC_KEY_LAST_SEEN_DATE,
            self::DYNAMIC_KEY_PERMISSIONS_EDIT,
            self::DYNAMIC_KEY_PERMISSIONS_SELF,
            self::DYNAMIC_KEY_TIMEZONE_OFFSET,
            self::DYNAMIC_KEY_TITLE,
            self::DYNAMIC_KEY_UNREAD_CONVO_COUNT,
            self::DYNAMIC_KEY_UNREAD_NOTIF_COUNT,
        ];

        return $mappings;
    }

    public function getNotFoundMessage()
    {
        return \XF::phrase('requested_user_not_found');
    }

    public function reset($source, $parent, $selector)
    {
        parent::reset($source, $parent, $selector);

        $this->flagFullAccess = $this->checkFullAccess();
    }

    /**
     * @return bool
     */
    protected function checkFullAccess()
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id < 1) {
            return false;
        }

        if ($this->source['user_id'] === $visitor->user_id) {
            return true;
        }

        return $this->checkAdminPermission('user');
    }

    /**
     * @return array|null
     */
    protected function collectExternalAuths()
    {
        if (!$this->flagFullAccess) {
            return null;
        }

        /** @var UserProfile $userProfile */
        $userProfile = $this->source->Profile;
        if (empty($userProfile)) {
            return null;
        }

        $data = [];
        foreach ($userProfile->connected_accounts as $provider => $providerKey) {
            $data[] = [
                'provider' => $provider,
                'provider_key' => $providerKey
            ];
        }

        return $data;
    }

    /**
     * @return array|null
     */
    protected function collectFields()
    {
        // TODO
        return null;
    }

    /**
     * @param string $key
     * @return array|null
     */
    protected function collectGroups($key)
    {
        if (!$this->flagFullAccess) {
            return null;
        }

        static $allGroups = null;
        if ($allGroups === null) {
            /** @var UserGroup $userGroupRepo */
            $userGroupRepo = $this->app->repository('XF:UserGroup');
            $allGroups = $userGroupRepo->findUserGroupsForList()->fetch();
        }

        /** @var \XF\Entity\User $user */
        $user = $this->source;
        $userGroups = [];
        foreach ($allGroups as $group) {
            if (!$user->isMemberOf($group)) {
                continue;
            }
            $userGroups[] = $group;
        }

        $data = [];
        /** @var \XF\Entity\UserGroup $group */
        foreach ($userGroups as $group) {
            $groupData = $this->transformer->transformSubEntity($this, $key, $group);
            if ($group->user_group_id === $user->user_group_id) {
                $groupData[self::DYNAMIC_KEY_GROUPS__IS_PRIMARY] = true;
            } else {
                $groupData[self::DYNAMIC_KEY_GROUPS__IS_PRIMARY] = false;
            }

            $data[] = $groupData;
        }

        return $data;
    }

    /**
     * @return array|null
     */
    protected function collectPermissionsEdit()
    {
        // TODO
        return null;
    }

    /**
     * @return array|null
     */
    protected function collectPermissionsSelf()
    {
        if (!$this->flagFullAccess) {
            return null;
        }

        $canStartConversation = \XF::visitor()->canStartConversation();
        $canUploadAndManageAttachments = false;
        if ($canStartConversation) {
            /** @var ConversationMaster $conversation */
            $conversation = $this->app->em()->create('XF:ConversationMaster');
            $canUploadAndManageAttachments = $conversation->canUploadAndManageAttachments();
        }

        return [
            self::PERM_SELF_CREATE_CONVO => $canStartConversation,
            self::PERM_SELF_ATTACH_CONVO => $canUploadAndManageAttachments
        ];
    }
}
