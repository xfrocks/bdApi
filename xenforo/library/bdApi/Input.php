<?php

// this is loaded only when $_SERVER['REQUEST_METHOD'] === 'OPTIONS'

/* @var $app XenForo_Application */
$app = XenForo_Application::getInstance();
$xenforoInputPath = $app->getRootDir() . '/library/XenForo/Input.php';
$xenforoInputContents = file_get_contents($xenforoInputPath);

// remove <?php
$xenforoInputContents = substr($xenforoInputContents, 5);

// rename class
$xenforoInputContents = str_replace('class XenForo_Input', 'class _XenForo_Input', $xenforoInputContents);

eval($xenforoInputContents);

class bdApi_Input extends _XenForo_Input
{
    protected static $_bdApi_filters = null;

    public static function bdApi_resetFilters()
    {
        self::$_bdApi_filters = array();
    }

    public static function bdApi_addFile($variableName)
    {
        self::$_bdApi_filters[$variableName] = array(
            'name' => $variableName,
            'type' => 'file',
        );
    }

    public static function bdApi_getFilters()
    {
        $filters = self::$_bdApi_filters;
        self::$_bdApi_filters = null;

        return $filters;
    }

    public function inRequest($key)
    {
        if (self::$_bdApi_filters === null) {
            return parent::inRequest($key);
        }

        return true;
    }

    public function filterSingle($variableName, $filterData, array $options = array())
    {
        $type = '';
        $default = null;

        if (is_string($filterData)) {
            $type = $filterData;
        } elseif (is_array($filterData)
            && isset($filterData[0])
        ) {
            if (is_string($filterData[0])) {
                $type = $filterData[0];
            } elseif (is_array($filterData[0])) {
                $type = reset($filterData[0]);
            }

            if (!empty($filterData['array'])) {
                $type = sprintf('array_of_%s', $type);
            }

            if (!empty($filterData['default'])) {
                $default = $filterData['default'];
            }
        }

        if (!empty($type)) {
            self::$_bdApi_filters[$variableName] = array(
                'name' => $variableName,
                'type' => $type,
            );

            if (!empty($default)) {
                self::$_bdApi_filters[$variableName]['required'] = false;
            }
        }

        return parent::filterSingle($variableName, $filterData, $options);
    }
}

eval('class XenForo_Input extends bdApi_Input {}');
