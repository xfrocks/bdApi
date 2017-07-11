<?php

// this is loaded only when $_SERVER['REQUEST_METHOD'] === 'OPTIONS'

/* @var $app XenForo_Application */
$app = XenForo_Application::getInstance();
$xenforoUploadPath = $app->getRootDir() . '/library/XenForo/Upload.php';
$xenforoUploadContents = file_get_contents($xenforoUploadPath);

// remove <?php
$xenforoUploadContents = substr($xenforoUploadContents, 5);

// rename class
$xenforoUploadContents = str_replace('class XenForo_Upload', 'class _XenForo_Upload', $xenforoUploadContents);

eval($xenforoUploadContents);

class bdApi_Upload extends _XenForo_Upload
{
    public static function getUploadedFiles($formField, array $source = null)
    {
        bdApi_Input::bdApi_addFile($formField);

        return parent::getUploadedFiles($formField, $source);
    }
}

eval('class XenForo_Upload extends bdApi_Upload {}');

if (false) {
    class _XenForo_Upload extends XenForo_Upload
    {
    }
}
