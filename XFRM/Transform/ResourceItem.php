<?php

namespace Xfrocks\Api\XFRM\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;

class ResourceItem extends AbstractHandler implements AttachmentParent
{
    const ATTACHMENT__DYNAMIC_KEY_ID = 'resource_id';
    const ATTACHMENT__LINK_RESOURCE = 'resource';

    const KEY_CATEGORY_ID = 'resource_category_id';
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_CREATE_DATE = 'resource_create_date';
    const KEY_DESCRIPTION = 'resource_description';
    const KEY_DOWNLOAD_COUNT = 'resource_download_count';
    const KEY_ID = 'resource_id';
    const KEY_RATING_COUNT = 'resource_rating_count';
    const KEY_RATING_SUM = 'resource_rating_sum';
    const KEY_RATING_AVG = 'resource_rating_avg';
    const KEY_RATING_WEIGHTED = 'resource_rating_weighted';
    const KEY_TITLE = 'resource_title';
    const KEY_UPDATE_DATE = 'resource_update_date';

    const DYNAMIC_KEY_ATTACHMENT_COUNT = 'resource_attachment_count';
    const DYNAMIC_KEY_CURRENCY = 'resource_currency';
    const DYNAMIC_KEY_HAS_FILE = 'resource_has_file';
    const DYNAMIC_KEY_HAS_URL = 'resource_has_url';
    const DYNAMIC_KEY_IS_DELETED = 'resource_is_deleted';
    const DYNAMIC_KEY_IS_FOLLOWED = 'resource_is_followed';
    const DYNAMIC_KEY_IS_LIKED = 'resource_is_liked';
    const DYNAMIC_KEY_IS_PUBLISHED = 'resource_is_published';
    const DYNAMIC_KEY_LIKE_COUNT = 'resource_like_count';
    const DYNAMIC_KEY_PRICE = 'resource_price';
    const DYNAMIC_KEY_RATING = 'resource_rating';
    const DYNAMIC_KEY_TAGS = 'resource_tags';
    const DYNAMIC_KEY_TEXT = 'resource_text';
    const DYNAMIC_KEY_TEXT_HTML = 'resource_text_html';
    const DYNAMIC_KEY_TEXT_PLAIN = 'resource_text_plain_text';
    const DYNAMIC_KEY_VERSION = 'resource_version';

    const LINK_CATEGORY = 'category';
    const LINK_CONTENT = 'content';
    const LINK_CREATOR_AVATAR = 'creator_avatar';
    const LINK_ICON = 'icon';
    const LINK_RATINGS = 'ratings';
    const LINK_THREAD = 'thread';

    const PERM_ADD_ICON = 'add_icon';
    const PERM_DOWNLOAD = 'download';
    const PERM_RATE = 'rate';

    protected $attachmentData = null;

    public function attachmentCalculateDynamicValue($attachmentHandler, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $this->source['resource_id'];
        }

        return null;
    }

    public function attachmentCollectLinks($attachmentHandler, array &$links)
    {
        $links[self::ATTACHMENT__LINK_RESOURCE] = $this->buildApiLink('resources', $this->source);
    }

    public function attachmentCollectPermissions($attachmentHandler, array &$permissions)
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $this->source;
        $canDelete = false;

        if ($resourceItem->canEdit() && $resourceItem->Category->canUploadAndManageUpdateImages()) {
            $attachmentData = $this->getAttachmentData();
            /** @var \XF\Attachment\AbstractHandler $attachmentHandler */
            $attachmentHandler = $attachmentData['handler'];
            $canDelete = $attachmentHandler->canManageAttachments($attachmentData['context']);
        }

        $permissions[self::PERM_DELETE] = $canDelete;
    }

    public function attachmentGetMappings($attachmentHandler, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    public function calculateDynamicValue($key)
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $this->source;

        switch ($key) {
            case self::DYNAMIC_KEY_ATTACHMENT_COUNT:
                return $resourceItem->Description->attach_count;
            case self::DYNAMIC_KEY_ATTACHMENTS:
                if ($resourceItem->Description->attach_count === 0) {
                    return [];
                }
                $attachmentData = $this->getAttachmentData();
                return $this->transformer->transformSubEntities($this, $key, $attachmentData['attachments']);
            case self::DYNAMIC_KEY_CURRENCY:
                return $resourceItem->external_purchase_url ? $resourceItem->currency : null;
            case self::DYNAMIC_KEY_FIELDS:
                $resourceFields = $resourceItem->custom_fields;
                return $this->transformer->transformCustomFieldSet($this, $resourceFields);
            case self::DYNAMIC_KEY_HAS_FILE:
                return $resourceItem->getResourceTypeDetailed() === 'download_local';
            case self::DYNAMIC_KEY_HAS_URL:
                if ($resourceItem->external_purchase_url) {
                    return true;
                }

                if ($resourceItem->getResourceTypeDetailed() === 'download_external') {
                    return $resourceItem->CurrentVersion->download_url;
                }

                return false;
            case self::DYNAMIC_KEY_IS_DELETED:
                return $resourceItem->resource_state === 'deleted';
            case self::DYNAMIC_KEY_IS_FOLLOWED:
                $userId = \XF::visitor()->user_id;
                if ($userId < 1) {
                    return false;
                }

                return !empty($resourceItem->Watch[$userId]);
            case self::DYNAMIC_KEY_IS_LIKED:
                return $resourceItem->Description->isLiked();
            case self::DYNAMIC_KEY_IS_PUBLISHED:
                return $resourceItem->resource_state === 'visible';
            case self::DYNAMIC_KEY_LIKE_COUNT:
                return $resourceItem->Description->likes;
            case self::DYNAMIC_KEY_PRICE:
                return $resourceItem->external_purchase_url ? $resourceItem->price : null;
            case self::DYNAMIC_KEY_RATING:
                $count = $resourceItem->rating_count;
                if ($count == 0) {
                    return 0;
                }

                $average = $resourceItem->rating_sum / $count;
                $average = round($average / 0.5, 0) * 0.5;

                return $average;
            case self::DYNAMIC_KEY_TAGS:
                return $this->transformer->transformTags($this, $resourceItem->tags);
            case self::DYNAMIC_KEY_TEXT:
                return $resourceItem->Description->message;
            case self::DYNAMIC_KEY_TEXT_HTML:
                return $this->renderBbCodeHtml($key, $resourceItem->Description->message);
            case self::DYNAMIC_KEY_TEXT_PLAIN:
                return $this->renderBbCodePlainText($resourceItem->Description->message);
            case self::DYNAMIC_KEY_VERSION:
                return $resourceItem->CurrentVersion->version_string;
        }

        return null;
    }

    public function collectLinks()
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $this->source;

        $links = [
            self::LINK_ATTACHMENTS => $this->buildApiLink('resources/attachments', $resourceItem),
            self::LINK_CATEGORY => $this->buildApiLink('resource-categories', $resourceItem->Category),
            self::LINK_CREATOR_AVATAR => $resourceItem->User->getAvatarUrl('l'),
            self::LINK_DETAIL => $this->buildApiLink('resources', $resourceItem),
            self::LINK_FOLLOWERS => $this->buildApiLink('resources/followers', $resourceItem),
            self::LINK_ICON => $resourceItem->getIconUrl(),
            self::LINK_LIKES => $this->buildApiLink('resources/likes', $resourceItem),
            self::LINK_PERMALINK => $this->buildPublicLink('resources', $resourceItem),
            self::LINK_RATINGS => $this->buildApiLink('resources/ratings', $resourceItem),
            self::LINK_REPORT => $this->buildApiLink('resources/report', $resourceItem),
        ];

        if ($resourceItem->external_purchase_url) {
            $links[self::LINK_CONTENT] = $resourceItem->external_purchase_url;
        } else {
            $resourceTypeDetailed = $resourceItem->getResourceTypeDetailed();
            switch ($resourceTypeDetailed) {
                case 'download_external':
                    $links[self::LINK_CONTENT] = $resourceItem->CurrentVersion->download_url;
                    break;
                case 'download_local':
                    $links[self::LINK_CONTENT] = $this->buildApiLink('resources/files', $resourceItem);
                    break;
            }
        }

        if ($resourceItem->discussion_thread_id > 0) {
            $links[self::LINK_THREAD] = $this->buildApiLink(
                'threads',
                ['thread_id' => $resourceItem->discussion_thread_id]
            );
        }

        return $links;
    }

    public function collectPermissions()
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $this->source;

        $permissions = [
            self::PERM_ADD_ICON => $resourceItem->canEdit(),
            self::PERM_DELETE => $resourceItem->canDelete(),
            self::PERM_DOWNLOAD => $resourceItem->canDownload(),
            self::PERM_EDIT => $resourceItem->canEdit(),
            self::PERM_FOLLOW => $resourceItem->canWatch(),
            self::PERM_LIKE => $resourceItem->Description->canLike(),
            self::PERM_RATE => $resourceItem->canRate(),
            self::PERM_REPORT => $resourceItem->Description->canReport(),
        ];

        return $permissions;
    }

    public function getFetchWith(array $extraWith = [])
    {
        $with = array_merge([
            'Category',
            'CurrentVersion',
            'Description',
            'User',
        ], $extraWith);

        $visitor = \XF::visitor();
        $userId = $visitor->user_id;
        if ($userId > 0) {
            $with[] = 'Category.Permissions|' . $visitor->permission_combination_id;
            $with[] = 'Description.Likes|' . $userId;
            $with[] = 'Watch|' . $userId;
        }

        return $with;
    }

    public function getMappings()
    {
        return [
            // xf_rm_resource
            'download_count' => self::KEY_DOWNLOAD_COUNT,
            'last_update' => self::KEY_UPDATE_DATE,
            'rating_count' => self::KEY_RATING_COUNT,
            'rating_sum' => self::KEY_RATING_SUM,
            'rating_avg' => self::KEY_RATING_AVG,
            'rating_weighted' => self::KEY_RATING_WEIGHTED,
            'resource_category_id' => self::KEY_CATEGORY_ID,
            'resource_date' => self::KEY_CREATE_DATE,
            'resource_id' => self::KEY_ID,
            'tag_line' => self::KEY_DESCRIPTION,
            'title' => self::KEY_TITLE,
            'user_id' => self::KEY_CREATOR_USER_ID,
            'username' => self::KEY_CREATOR_USERNAME,

            self::DYNAMIC_KEY_ATTACHMENT_COUNT,
            self::DYNAMIC_KEY_ATTACHMENTS,
            self::DYNAMIC_KEY_CURRENCY,
            self::DYNAMIC_KEY_FIELDS,
            self::DYNAMIC_KEY_HAS_FILE,
            self::DYNAMIC_KEY_HAS_URL,
            self::DYNAMIC_KEY_IS_DELETED,
            self::DYNAMIC_KEY_IS_FOLLOWED,
            self::DYNAMIC_KEY_IS_LIKED,
            self::DYNAMIC_KEY_IS_PUBLISHED,
            self::DYNAMIC_KEY_LIKE_COUNT,
            self::DYNAMIC_KEY_PRICE,
            self::DYNAMIC_KEY_RATING,
            self::DYNAMIC_KEY_TAGS,
            self::DYNAMIC_KEY_TEXT,
            self::DYNAMIC_KEY_TEXT_HTML,
            self::DYNAMIC_KEY_TEXT_PLAIN,
            self::DYNAMIC_KEY_VERSION,
        ];
    }

    public function getNotFoundMessage()
    {
        return \XF::phrase('xfrm_requested_resource_not_found');
    }

    /**
     * @return array
     */
    protected function getAttachmentData()
    {
        static $contentType = 'resource_update';

        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $this->source;

        if (!isset($this->attachmentData[$resourceItem->resource_id])) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->app->repository('XF:Attachment');
            $this->attachmentData[$resourceItem->resource_id] = $attachmentRepo->getEditorData(
                $contentType,
                $resourceItem->Description
            );
            $this->attachmentData[$resourceItem->resource_id]['handler'] = $attachmentRepo->getAttachmentHandler($contentType);
        }

        return $this->attachmentData[$resourceItem->resource_id];
    }
}
