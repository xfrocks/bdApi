<?php

class bdApi_ControllerApi_Tag extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        if (XenForo_Application::$versionId < 1050000) {
            return $this->responseNoPermission();
        }

        $tagId = $this->_input->filterSingle('tag_id', XenForo_Input::UINT);
        if (!empty($tagId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        /** @var bdApi_Extend_Model_Tag $tagModel */
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

    public function actionSingle()
    {
        $tagId = $this->_input->filterSingle('tag_id', XenForo_Input::UINT);

        /** @var bdApi_Extend_Model_Tag $tagModel */
        $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
        $tag = $tagModel->getTagById($tagId);
        if (empty($tag)) {
            return $this->responseError(new XenForo_Phrase('requested_tag_not_found'), 404);
        }

        $pageNavParams = array();
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $cache = $tagModel->getTagResultsCache($tag['tag_id']);
        if ($cache) {
            $contentTags = json_decode($cache['results'], true);
        } else {
            $xenOptions = XenForo_Application::getOptions();
            $maximumSearchResults = $xenOptions->get('maximumSearchResults');
            $contentTags = $tagModel->getContentIdsByTagId($tag['tag_id'], $maximumSearchResults);

            $insertCache = (count($contentTags) > $xenOptions->get('searchResultsPerPage'));
            if ($insertCache) {
                $tagModel->insertTagResultsCache($tag['tag_id'], $contentTags);
            }
        }

        $totalResults = count($contentTags);
        $pageResultIds = array_slice($contentTags, ($page - 1) * $limit, $limit);

        /** @var bdApi_Extend_Model_Search $searchModel */
        $searchModel = $this->getModelFromCache('XenForo_Model_Search');
        $results = $searchModel->prepareApiDataForSearchResults($pageResultIds);
        if (!$this->_isFieldExcluded('content')) {
            $contentData = $searchModel->prepareApiContentDataForSearch($results);
        } else {
            $contentData = $results;
        }

        $data = array(
            '_tag' => $tag,
            'tag' => $tagModel->prepareApiDataForTag($tag),
            'tagged' => array_values($contentData),
            'tagged_total' => $totalResults,
        );

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $totalResults, $page,
            'tags', $tag, $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Tag_Single', $data);
    }

    public function actionGetFind()
    {
        $q = $this->_input->filterSingle('tag', XenForo_Input::STRING);

        if (XenForo_Application::$versionId < 1050000) {
            return $this->responseNoPermission();
        }

        $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_POST);

        /** @var bdApi_Extend_Model_Tag $tagModel */
        $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
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