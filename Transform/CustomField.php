<?php

namespace Xfrocks\Api\Transform;

use XF\CustomField\Definition;

class CustomField extends AbstractHandler
{
    const DYNAMIC_KEY_CHOICES = 'choices';
    const DYNAMIC_KEY_DESCRIPTION = 'description';
    const DYNAMIC_KEY_ID = 'id';
    const DYNAMIC_KEY_IS_MULTI_CHOICE = 'is_multi_choice';
    const DYNAMIC_KEY_IS_REQUIRED = 'is_required';
    const DYNAMIC_KEY_POSITION = 'position';
    const DYNAMIC_KEY_TITLE = 'title';
    const DYNAMIC_KEY_VALUE = 'value';
    const DYNAMIC_KEY_VALUES = 'values';

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var Definition|null $definition */
        $definition = $context->data('definition');
        if (!$definition) {
            return null;
        }

        /** @var array|string|null $valueData */
        $valueData = $context->data('value');

        switch ($key) {
            case self::DYNAMIC_KEY_CHOICES:
                if ($valueData !== null) {
                    return null;
                }

                if (!$this->hasChoices($definition)) {
                    return null;
                }

                $choices = [];
                foreach ($definition['field_choices'] as $choiceKey => $choiceValue) {
                    $choices[] = ['key' => $choiceKey, 'value' => $choiceValue];
                }

                return $choices;
            case self::DYNAMIC_KEY_DESCRIPTION:
                return $definition['description'];
            case self::DYNAMIC_KEY_ID:
                return $definition['field_id'];
            case self::DYNAMIC_KEY_IS_MULTI_CHOICE:
                return $definition['type_group'] === 'multiple';
            case self::DYNAMIC_KEY_IS_REQUIRED:
                if ($valueData !== null) {
                    return null;
                }

                return $definition->isRequired();
            case self::DYNAMIC_KEY_POSITION:
                return $definition['display_group'];
            case self::DYNAMIC_KEY_TITLE:
                return $definition['title'];
            case self::DYNAMIC_KEY_VALUE:
                if ($valueData === null || $this->hasChoices($definition)) {
                    return null;
                }

                return utf8_trim(strval($valueData));
            case self::DYNAMIC_KEY_VALUES:
                if ($valueData === null || !$this->hasChoices($definition)) {
                    return null;
                }

                $choices = $definition['field_choices'];
                $choiceKeys = is_array($valueData) ? $valueData : [strval($valueData)];
                $values = [];
                foreach ($choiceKeys as $choiceKey) {
                    if (!isset($choices[$choiceKey])) {
                        continue;
                    }

                    $values[] = [
                        'key' => $choiceKey,
                        'value' => $choices[$choiceKey],
                    ];
                }

                return $values;
        }

        return null;
    }

    public function canView(TransformContext $context)
    {
        return true;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            self::DYNAMIC_KEY_CHOICES,
            self::DYNAMIC_KEY_DESCRIPTION,
            self::DYNAMIC_KEY_ID,
            self::DYNAMIC_KEY_IS_MULTI_CHOICE,
            self::DYNAMIC_KEY_IS_REQUIRED,
            self::DYNAMIC_KEY_POSITION,
            self::DYNAMIC_KEY_TITLE,
            self::DYNAMIC_KEY_VALUE,
            self::DYNAMIC_KEY_VALUES,
        ];
    }

    public function onNewContext(TransformContext $context)
    {
        $data = parent::onNewContext($context);
        $data['definition'] = null;
        $data['value'] = null;

        $source = $context->getSource();
        if (is_array($source) && count($source) > 0) {
            $data['definition'] = $source[0];

            if (count($source) > 1) {
                $data['value'] = $source[1];
            }
        }

        return $data;
    }

    /**
     * @param Definition $definition
     * @return bool
     */
    protected function hasChoices($definition)
    {
        return in_array($definition['type_group'], ['single', 'multiple'], true);
    }
}
