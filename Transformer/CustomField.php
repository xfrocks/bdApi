<?php

namespace Xfrocks\Api\Transformer;

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

    /**
     * @var mixed
     */
    protected $value = null;

    public function calculateDynamicValue($key)
    {
        /** @var Definition $definition */
        $definition = $this->entity;

        switch ($key) {
            case self::DYNAMIC_KEY_CHOICES:
                if ($this->hasValue()) {
                    return null;
                }

                if (!$this->hasChoices()) {
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
                if ($this->hasValue()) {
                    return null;
                }

                return $definition->isRequired();
            case self::DYNAMIC_KEY_VALUE:
                if (!$this->hasValue() || $this->hasChoices()) {
                    return null;
                }

                return utf8_trim($this->value);
            case self::DYNAMIC_KEY_VALUES:
                if (!$this->hasValue() || !$this->hasChoices()) {
                    return null;
                }

                $choices = $definition['field_choices'];
                $choiceKeys = is_array($this->value) ? $this->value : [strval($this->value)];
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

    public function getMappings()
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

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    protected function hasChoices()
    {
        return in_array($this->entity['type_group'], ['single', 'multiple'], true);
    }

    /**
     * @return bool
     */
    protected function hasValue()
    {
        return $this->value !== null;
    }
}
