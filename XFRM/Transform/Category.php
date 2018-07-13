<?php

namespace Xfrocks\Api\XFRM\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class Category extends AbstractHandler
{
    const KEY_DESCRIPTION = 'category_description';
    const KEY_ID = 'resource_category_id';
    const KEY_PARENT_ID = 'parent_category_id';
    const KEY_RESOURCE_COUNT = 'category_resource_count';
    const KEY_TITLE = 'category_title';

    const LINK_RESOURCES = 'resources';
    const LINK_RESOURCES_IN_SUB = 'resources_in_sub';

    const PERM_ADD = 'add';
    const PERM_ADD_FILE = 'add_file';
    const PERM_ADD_URL = 'add_url';
    const PERM_ADD_PRICE = 'add_price';
    const PERM_ADD_FILE_LESS = 'add_no_file_or_url';

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XFRM\Entity\Category $category */
        $category = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_FIELDS:
                /** @var \XF\CustomField\DefinitionSet $allDefinitions */
                $allDefinitions = $this->app->container('customFields.resources');
                $categoryDefinitions = $allDefinitions->filterOnly($category->field_cache);
                return $this->transformer->transformCustomFieldDefinitionSet($context, $categoryDefinitions);
        }

        return null;
    }

    public function collectLinks($context)
    {
        /** @var \XFRM\Entity\Category $category */
        $category = $context->getSource();

        $links = [
            self::LINK_DETAIL => $this->buildApiLink('resource-categories', $category),
            self::LINK_PERMALINK => $this->buildPublicLink('resources/categories', $category),
            self::LINK_RESOURCES => $this->buildApiLink(
                'resources',
                null,
                ['resource_category_id' => $category->resource_category_id]
            ),
            self::LINK_RESOURCES_IN_SUB => $this->buildApiLink(
                'resources',
                null,
                ['resource_category_id' => $category->resource_category_id, 'in_sub' => 1]
            ),
        ];

        return $links;
    }

    public function collectPermissions($context)
    {
        /** @var \XFRM\Entity\Category $category */
        $category = $context->getSource();

        $permissions = [
            self::PERM_ADD => $category->canAddResource(),
        ];

        $permissions += [
            self::PERM_ADD_FILE => $permissions[self::PERM_ADD] && $category->allow_local,
            self::PERM_ADD_URL => $permissions[self::PERM_ADD] && ($category->allow_external || $category->allow_commercial_external),
            self::PERM_ADD_PRICE => $permissions[self::PERM_ADD] && $category->allow_commercial_external,
            self::PERM_ADD_FILE_LESS => $permissions[self::PERM_ADD] && $category->allow_fileless,
        ];

        return $permissions;
    }

    public function getMappings($context)
    {
        return [
            'description' => self::KEY_DESCRIPTION,
            'parent_category_id' => self::KEY_PARENT_ID,
            'resource_category_id' => self::KEY_ID,
            'resource_count' => self::KEY_RESOURCE_COUNT,
            'title' => self::KEY_TITLE,

            self::DYNAMIC_KEY_FIELDS,
        ];
    }

    public function getNotFoundMessage()
    {
        return \XF::phrase('xfmg_requested_category_not_found');
    }
}
