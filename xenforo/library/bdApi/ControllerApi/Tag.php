<?php

class bdApi_ControllerApi_Tag extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        if (XenForo_Application::$versionId < 1050000) {
            return $this->responseNoPermission();
        }

        /** @var bdApi_XenForo_Model_Tag $tagModel */
        $tagModel = $this->getModelFromCache('XenForo_Model_Tag');

        $options = XenForo_Application::getOptions();

        if ($options->get('tagCloud', 'enabled')) {
            $tags = $tagModel->getTagsForCloud($options->get('tagCloud', 'count'), $options->get('tagCloudMinUses'));
        } else {
            $tags = array();
        }

        $data = array(
            'tags' => $tagModel->prepareApiDataForTags($tags),
        );

        return $this->responseData('bdApi_ViewData_Tag_List', $data);
    }

    public function actionGetFind()
    {
        if (XenForo_Application::$versionId < 1050000) {
            return $this->responseNoPermission();
        }

        $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_POST);

        /** @var bdApi_XenForo_Model_Tag $tagModel */
        $tagModel = $this->getModelFromCache('XenForo_Model_Tag');

        $q = $this->_input->filterSingle('tag', XenForo_Input::STRING);
        $q = $tagModel->normalizeTag($q);

        if (strlen($q) >= 2) {
            $tags = $tagModel->autoCompleteTag($q);
        } else {
            $tags = array();
        }

        $data = array(
            'tags' => array_values($tagModel->prepareApiDataForTags($tags)),
        );

        return $this->responseData('bdApi_ViewData_Tag_Find', $data);
    }
}