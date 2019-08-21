<?php

namespace Xfrocks\Api\XFRM\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\Util\ParentFinder;

class ResourceItem extends AbstractHandler implements AttachmentParent
{
    const CONTENT_TYPE_RESOURCE_UPDATE = 'resource_update';

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

    public function attachmentCalculateDynamicValue(TransformContext $context, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $context->getParentSourceValue('resource_id');
        }

        return null;
    }

    public function attachmentCollectLinks(TransformContext $context, array &$links)
    {
        $resourceItem = $context->getParentSource();
        $links[self::ATTACHMENT__LINK_RESOURCE] = $this->buildApiLink('resources', $resourceItem);
    }

    public function attachmentCollectPermissions(TransformContext $context, array &$permissions)
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $context->getParentSource();
        $canDelete = false;

        if ($resourceItem->canEdit() && $resourceItem->Category->canUploadAndManageUpdateImages()) {
            $canDelete = $this->checkAttachmentCanManage(
                self::CONTENT_TYPE_RESOURCE_UPDATE,
                $resourceItem->Description
            );
        }

        $permissions[self::PERM_DELETE] = $canDelete;
    }

    public function attachmentGetMappings(TransformContext $context, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_ATTACHMENT_COUNT:
                return $resourceItem->Description->attach_count;
            case self::DYNAMIC_KEY_ATTACHMENTS:
                $description = $resourceItem->Description;
                if ($description->attach_count < 1) {
                    return [];
                }

                return $this->transformer->transformEntityRelation($context, $key, $description, 'Attachments');
            case self::DYNAMIC_KEY_CURRENCY:
                return strlen($resourceItem->external_purchase_url) > 0 ? $resourceItem->currency : null;
            case self::DYNAMIC_KEY_FIELDS:
                $resourceFields = $resourceItem->custom_fields;
                return $this->transformer->transformCustomFieldSet($context, $resourceFields);
            case self::DYNAMIC_KEY_HAS_FILE:
                return $resourceItem->getResourceTypeDetailed() === 'download_local';
            case self::DYNAMIC_KEY_HAS_URL:
                if (strlen($resourceItem->external_purchase_url) > 0) {
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

                return isset($resourceItem->Watch[$userId]);
            case self::DYNAMIC_KEY_IS_LIKED:
                return $resourceItem->Description->isReactedTo();
            case self::DYNAMIC_KEY_IS_PUBLISHED:
                return $resourceItem->resource_state === 'visible';
            case self::DYNAMIC_KEY_LIKE_COUNT:
                return $resourceItem->Description->get('reaction_score');
            case self::DYNAMIC_KEY_PRICE:
                return strlen($resourceItem->external_purchase_url) > 0 ? $resourceItem->price : null;
            case self::DYNAMIC_KEY_RATING:
                $count = $resourceItem->rating_count;
                if ($count == 0) {
                    return 0;
                }

                $average = $resourceItem->rating_sum / $count;
                $average = round($average / 0.5, 0) * 0.5;

                return $average;
            case self::DYNAMIC_KEY_TAGS:
                return $this->transformer->transformTags($context, $resourceItem->tags);
            case self::DYNAMIC_KEY_TEXT:
                return $resourceItem->Description->message;
            case self::DYNAMIC_KEY_TEXT_HTML:
                $description = $resourceItem->Description;
                return $this->renderBbCodeHtml($key, $description->message, $description);
            case self::DYNAMIC_KEY_TEXT_PLAIN:
                return $this->renderBbCodePlainText($resourceItem->Description->message);
            case self::DYNAMIC_KEY_VERSION:
                return $resourceItem->CurrentVersion->version_string;
        }

        return null;
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $context->getSource();

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

        if (strlen($resourceItem->external_purchase_url) > 0) {
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

    public function collectPermissions(TransformContext $context)
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $context->getSource();

        $permissions = [
            self::PERM_ADD_ICON => $resourceItem->canEdit(),
            self::PERM_DELETE => $resourceItem->canDelete(),
            self::PERM_DOWNLOAD => $resourceItem->canDownload(),
            self::PERM_EDIT => $resourceItem->canEdit(),
            self::PERM_FOLLOW => $resourceItem->canWatch(),
            self::PERM_LIKE => $resourceItem->Description->canReact(),
            self::PERM_RATE => $resourceItem->canRate(),
            self::PERM_REPORT => $resourceItem->Description->canReport(),
        ];

        return $permissions;
    }

    public function getMappings(TransformContext $context)
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

    public function onTransformEntities(TransformContext $context, $entities)
    {
        $needAttachments = false;
        if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_ATTACHMENTS)) {
            $needAttachments = true;
        }
        if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_TEXT_HTML)) {
            $needAttachments = true;
        }
        if ($needAttachments) {
            $descriptions = [];
            /** @var \XFRM\Entity\ResourceItem $resourceItem */
            foreach ($entities as $resourceItem) {
                $description = $resourceItem->Description;
                $descriptions[$description->resource_update_id] = $description;
            }

            $this->enqueueEntitiesToAddAttachmentsTo($descriptions, self::CONTENT_TYPE_RESOURCE_UPDATE);
        }

        return $entities;
    }

    public function onTransformFinder(TransformContext $context, \XF\Mvc\Entity\Finder $finder)
    {
        $categoryFinder = new ParentFinder($finder, 'Category');
        $visitor = \XF::visitor();

        $categoryFinder->with('Permissions|' . $visitor->permission_combination_id);

        $finder->with('CurrentVersion');
        $finder->with('Description');
        $finder->with('User');

        $userId = $visitor->user_id;
        if ($userId > 0) {
            if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_IS_FOLLOWED)) {
                $finder->with('Watch|' . $userId);
            }

            if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_IS_LIKED)) {
                $finder->with('Description.Reactions|' . $userId);
            }
        }

        return parent::onTransformFinder($context, $finder);
    }
}
