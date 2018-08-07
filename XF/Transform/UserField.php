<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class UserField extends AbstractHandler
{
    const KEY_DESCRIPTION = 'description';
    const KEY_DISPLAY_GROUP = 'display_group';
    const KEY_ID = 'id';
    const KEY_REQUIRED = 'is_required';
    const KEY_TITLE = 'title';

    const DYNAMIC_KEY_CHOICES = 'choices';
    const DYNAMIC_KEY_IS_MULTIPLE_CHOICE = 'is_multiple_choice';
    const DYNAMIC_KEY_VALUE = 'value';
    const DYNAMIC_KEY_VALUES = 'values';

    public function getMappings($context)
    {
//        return [
//            'field_id' => self::KEY_ID,
//            'title' => self::KEY_TITLE,
//            'description' => self::KEY_DESCRIPTION,
//            'display_group' => self::KEY_DISPLAY_GROUP,
//            'required' => self::KEY_REQUIRED,
//
//            self::DYNAMIC_KEY_CHOICES,
//            self::DYNAMIC_KEY_IS_MULTIPLE_CHOICE,
//            self::DYNAMIC_KEY_VALUE,
//            self::DYNAMIC_KEY_VALUES
//        ];
        return [];
    }

    public function onTransformed($context, array &$data)
    {
        parent::onTransformed($context, $data);
    }

//    public function calculateDynamicValue($context, $key)
//    {
//        /** @var \XF\Entity\UserField $userField */
//        $userField = $context->getSource();
//        /** @var \XF\Repository\UserField $userFieldRepo */
//        $userFieldRepo = \XF::repository('XF:UserField');
//
//        switch ($key) {
//            case self::DYNAMIC_KEY_CHOICES:
//                $choices = [];
//
//                foreach ($userField->field_choices as $key => $value) {
//                    $choices[] = [
//                        'key' => strval($key),
//                        'value' => strval($value)
//                    ];
//                }
//
//                return $choices;
//            case self::DYNAMIC_KEY_IS_MULTIPLE_CHOICE:
//                if (!$userField->isChoiceField()) {
//                    return false;
//                }
//
//                return $userFieldRepo->getFieldTypes()[$userField->field_type]['type'] === 'multiple';
//            case self::DYNAMIC_KEY_VALUE:
//                // TODO: Update field value
//                return null;
//            case self::DYNAMIC_KEY_VALUES:
//                // TODO: Update field values
//                return null;
//        }
//
//        return null;
//    }
}
