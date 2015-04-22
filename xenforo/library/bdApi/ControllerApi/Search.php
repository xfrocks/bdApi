<?php

class bdApi_ControllerApi_Search extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $data = array('links' => array(
            'posts' => XenForo_Link::buildApiLink('search/posts'),
            'threads' => XenForo_Link::buildApiLink('search/threads'),
            'profile-posts' => XenForo_Link::buildApiLink('search/profile-posts'),
        ));

        return $this->responseData('bdApi_ViewApi_Index', $data);
    }

    public function actionPostIndex()
    {
        $results = $this->_doSearch();

        $data = array('results' => $results);

        $resultsData = $this->_fetchResultsData($results);
        if (!empty($resultsData)) {
            $data['data'] = $resultsData;
        }

        return $this->responseData('bdApi_ViewApi_Search', $data);
    }

    public function actionGetThreads()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
    }

    public function actionPostThreads()
    {
        $results = $this->_doSearch('thread');
        foreach ($results as &$resultRef) {
            // backward compatibility
            $resultRef['thread_id'] = $resultRef['content_id'];
        }

        $data = array('threads' => $results);

        $resultsData = $this->_fetchResultsData($results);
        if (!empty($resultsData)) {
            $data['data'] = $resultsData;
        }

        return $this->responseData('bdApi_ViewApi_Search_Threads', $data);
    }

    public function actionGetPosts()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
    }

    public function actionPostPosts()
    {
        $constraints = array();
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        if (!empty($threadId)) {
            $constraints['thread'] = $threadId;
        }

        $results = $this->_doSearch('post', $constraints);
        $threadIds = array();

        foreach ($results as &$resultRef) {
            if ($resultRef['content_type'] == 'post') {
                // backward compatibility
                $resultRef['post_id'] = $resultRef['content_id'];
            } elseif ($resultRef['content_type'] == 'thread') {
                $threadIds[] = $resultRef['content_id'];
            }
        }

        $threadIds = array_unique(array_map('intval', $threadIds));
        if (!empty($threadIds)) {
            /** @var XenForo_Model_Thread $threadModel */
            $threadModel = $this->getModelFromCache('XenForo_Model_Thread');
            $threads = $threadModel->getThreadsByIds($threadIds);
            foreach ($results as &$resultRef) {
                if ($resultRef['content_type'] == 'thread'
                    && isset($threads[$resultRef['content_id']])
                ) {
                    $threadRef =& $threads[$resultRef['content_id']];
                    $resultRef['content_type'] = 'post';
                    $resultRef['content_id'] = $threadRef['first_post_id'];
                    $resultRef['post_id'] = $threadRef['first_post_id'];
                }
            }
        }

        $data = array('posts' => $results);

        $resultsData = $this->_fetchResultsData($results);
        if (!empty($resultsData)) {
            $data['data'] = $resultsData;
        }

        return $this->responseData('bdApi_ViewApi_Search_Posts', $data);
    }

    public function actionGetProfilePosts()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
    }

    public function actionPostProfilePosts()
    {
        $results = $this->_doSearch('profile_post');

        $data = array('profile_posts' => $results);

        $resultsData = $this->_fetchResultsData($results);
        if (!empty($resultsData)) {
            $data['data'] = $resultsData;
        }

        return $this->responseData('bdApi_ViewApi_Search_ProfilePosts', $data);
    }

    public function _doSearch($contentType = null, array $constraints = array())
    {
        if (!XenForo_Visitor::getInstance()->canSearch()) {
            throw $this->getNoPermissionResponseException();
        }

        $input = array();

        $input['keywords'] = $this->_input->filterSingle('q', XenForo_Input::STRING);
        $input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, '');
        // don't allow searching of censored stuff
        if (empty($input['keywords'])) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_slash_search_requires_q'), 400));
        }

        $limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        if ($limit > 0) {
            $maxResults = min($maxResults, $limit);
        }

        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        if (!empty($forumId)) {
            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forumId)));
            $nodeIds = array_unique(array_merge(array($forumId), $childNodeIds));
            $constraints['node'] = implode(' ', $nodeIds);
            if (!$constraints['node']) {
                unset($constraints['node']);
            }
        }

        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (!empty($userId)) {
            $constraints['user'] = array($userId);
        }

        $searchModel = $this->_getSearchModel();
        $searcher = new XenForo_Search_Searcher($searchModel);

        if (!empty($contentType)) {
            // content type searching
            $typeHandler = $searchModel->getSearchDataHandler($contentType);
            $results = $searcher->searchType($typeHandler, $input['keywords'], $constraints, 'relevance', false, $maxResults);
        } else {
            // general searching
            $results = $searcher->searchGeneral($input['keywords'], $constraints, 'relevance', $maxResults);
        }

        return $searchModel->prepareApiDataForSearchResults($results);
    }

    protected function _fetchResultsData(array $results)
    {
        $dataLimit = $this->_input->filterSingle('data_limit', XenForo_Input::UINT);
        if (empty($dataLimit) || empty($results)) {
            return array();
        }

        $dataResults = array_slice($results, 0, $dataLimit);

        $searchModel = $this->_getSearchModel();
        $contentData = $searchModel->prepareApiContentDataForSearch($this, $dataResults);

        return array_values($contentData);
    }

    /**
     * @return bdApi_XenForo_Model_Search
     */
    protected function _getSearchModel()
    {
        return $this->getModelFromCache('XenForo_Model_Search');
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Node');
    }
}
