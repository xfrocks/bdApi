<?php

namespace Xfrocks\Api\Transform;

use XF\CustomField\Definition;

class CustomField extends AbstractHandler
{
    const KEY_ID = 'id';
    const KEY_TITLE = 'title';
    const KEY_DESCRIPTION = 'description';
    const KEY_POSITION = 'position';

    const DYNAMIC_KEY_CHOICES = 'choices';
    const DYNAMIC_KEY_IS_MULTI_CHOICE = 'is_multi_choice';
    const DYNAMIC_KEY_IS_REQUIRED = 'is_required';
    const DYNAMIC_KEY_VALUE = 'value';
    const DYNAMIC_KEY_VALUES = 'values';

    public function calculateDynamicValue($context, $key)
    {
        /** @var Definition $definition */
        $definition = $context->source;
        /** @var array|string|null $valueData */
        $valueData = $context->contextData['value'];

        switch ($key) {
            case self::DYNAMIC_KEY_CHOICES:
                if ($valueData !== null) {
                    return null;
                }

                if (!$this->hasChoices($definition)) {
                    return null;
                }

                $choices = [];
                foreach ($definition['field_choices'] as $key => $value) {
                    $choices[] = ['key' => $key, 'value' => $value];
                }

                return $choices;
            case self::DYNAMIC_KEY_IS_MULTI_CHOICE:
                return $definition['type_group'] === 'multiple';
            case self::DYNAMIC_KEY_IS_REQUIRED:
                if ($valueData !== null) {
                    return null;
                }

                return $definition->isRequired();
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
                    if (empty($choices[$choiceKey])) {
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

    public function getMappings($context)
    {
        return [
            'description' => self::KEY_DESCRIPTION,
            'field_id' => self::KEY_ID,
            'display_group' => self::KEY_POSITION,
            'title' => self::KEY_TITLE,

            self::DYNAMIC_KEY_CHOICES,
            self::DYNAMIC_KEY_IS_MULTI_CHOICE,
            self::DYNAMIC_KEY_IS_REQUIRED,
            self::DYNAMIC_KEY_VALUE,
            self::DYNAMIC_KEY_VALUES,
        ];
    }

    public function onNewContext($context)
    {
        $definition = null;
        $value = null;

        if (is_array($context->source) && count($context->source) > 0) {
            $definition = $context->source[0];

            if (count($context->source) > 1) {
                $value = $context->source[1];
            }
        }

        $context->source = $definition;

        return [
            'value' => $value
        ];
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
