<?php

class bdApi_Extend_Model_UserField extends XFCP_bdApi_Extend_Model_UserField
{
    public function bdApi_getUserFields()
    {
        static $fields = null;

        if ($fields === null) {
            $fields = $this->getUserFields();
        }

        return $fields;
    }

    public function prepareApiDataForField(array $field, $fieldValue = null)
    {
        $field = $this->prepareUserField($field, true, $fieldValue);
        $data = array();

        $publicKeys = array(
            'field_id' => 'id',
            'title' => 'title',
            'description' => 'description',
            'display_group' => 'position',
        );

        $data += bdApi_Data_Helper_Core::filter($field, $publicKeys);

        $hasChoices = false;
        if (!empty($field['isChoice'])) {
            $hasChoices = !empty($field['fieldChoices']);
            $data['is_multi_choice'] = !empty($field['isMultiChoice']);
        }

        if (true) {
            // always prepare choices
            $data['is_required'] = !empty($field['required']);
            if ($hasChoices) {
                $data['choices'] = array();
                foreach ($field['fieldChoices'] as $key => $value) {
                    $data['choices'][] = array(
                        'key' => strval($key),
                        'value' => strval($value),
                    );
                }
            }
        }

        if ($fieldValue !== null) {
            if ($hasChoices) {
                if (is_array($field['field_value'])) {
                    // multi choices
                    $fieldValueIds = array_keys($field['field_value']);
                } else {
                    // single choice
                    $fieldValueIds = array($field['field_value']);
                }

                $data['values'] = array();
                foreach ($fieldValueIds as $fieldValueId) {
                    if (!isset($field['fieldChoices'][$fieldValueId])) {
                        continue;
                    }

                    $data['values'][] = array(
                        'key' => strval($fieldValueId),
                        'value' => $field['fieldChoices'][$fieldValueId],
                    );
                }
            } else {
                // text
                $data['value'] = $field['field_value'];
            }
        }

        return $data;
    }
}
