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
        $search = $this->_doSearch();

        $this->_request->setParam('search_id', $search['search_id']);
        return $this->responseReroute(__CLASS__, 'get-results');
    }

    public function actionGetResults()
    {
        $searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
        $search = $this->_getSearchModel()->getSearchById($searchId);

        if (empty($search)
            || $search['user_id'] != XenForo_Visitor::getUserId()
        ) {
            return $this->responseError(new XenForo_Phrase('requested_search_not_found'), 404);
        }

        $pageNavParams = array();
        $page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
        $limit = XenForo_Application::get('options')->searchResultsPerPage;
        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if ($inputLimit > 0) {
            $limit = min($limit, $inputLimit);
            $pageNavParams['limit'] = $limit;
        }

        $pageResultIds = $this->_getSearchModel()->sliceSearchResultsToPage($search, $page, $limit);
        $results = $this->_getSearchModel()->prepareApiDataForSearchResults($pageResultIds);

        $contentData = $this->_getSearchModel()->prepareApiContentDataForSearch($results);

        $data = array(
            'data' => $this->_filterDataMany(array_values($contentData)),
            'data_total' => $search['result_count'],
        );

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $search['result_count'], $page, 'search/results', $search, $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Search_Results', $data);
    }

    public function actionGetThreads()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
    }

    public function actionPostThreads()
    {
        $search = $this->_doSearch('thread');
        $pageResultIds = $this->_getSearchModel()->sliceSearchResultsToPage($search, 1, null);
        $results = $this->_getSearchModel()->prepareApiDataForSearchResults($pageResultIds);

        $threads = array();
        foreach ($results as $result) {
            $threads[] = array('thread_id' => $result['content_id']);
        }

        $data = array('threads' => $threads);

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

        $search = $this->_doSearch('post', $constraints);
        $pageResultIds = $this->_getSearchModel()->sliceSearchResultsToPage($search, 1, null);
        $results = $this->_getSearchModel()->prepareApiDataForSearchResults($pageResultIds);
        $threadIds = array();

        $posts = array();
        foreach ($results as $key => $result) {
            if ($result['content_type'] == 'post') {
                $posts[$key] = array('post_id' => $result['content_id']);
            } elseif ($result['content_type'] == 'thread') {
                $posts[$key] = array('thread_id' => $result['content_id']);
                $threadIds[intval($result['content_id'])] = $key;
            }
        }

        if (!empty($threadIds)) {
            /** @var XenForo_Model_Thread $threadModel */
            $threadModel = $this->getModelFromCache('XenForo_Model_Thread');
            $threads = $threadModel->getThreadsByIds(array_keys($threadIds));
            foreach ($threadIds as $threadId => $key) {
                if (isset($threads[$threadId])) {
                    $threadRef =& $threads[$threadId];
                    $posts[$key]['post_id'] = $threadRef['first_post_id'];
                }
            }
            ksort($posts);
        }

        $data = array('posts' => $posts);

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
        $search = $this->_doSearch('profile_post');

        $this->_request->setParam('search_id', $search['search_id']);
        return $this->responseReroute(__CLASS__, 'get-results');
    }

    public function _doSearch($contentType = '', array $constraints = array())
    {
        if (!XenForo_Visitor::getInstance()->canSearch()) {
            throw $this->getNoPermissionResponseException();
        }

        $input = array(
            'order' => 'relevance',
            'group_discussion' => false,
        );

        $input['keywords'] = $this->_input->filterSingle('q', XenForo_Input::STRING);
        $input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, '');
        // don't allow searching of censored stuff
        if (empty($input['keywords'])) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_slash_search_requires_q'), 400));
        }

        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');

        switch ($contentType) {
            case 'thread':
            case 'post':
                // only these two legacy content types support `limit` param while searching
                // others use `limit` to control how many pieces of data are returned
                $limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
                if ($limit > 0) {
                    $maxResults = min($maxResults, $limit);
                }
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
            if (empty($typeHandler)) {
                throw new XenForo_Exception(sprintf('No search data handler found for content type %s', $contentType));
            }

            $results = $searcher->searchType($typeHandler, $input['keywords'], $constraints, $input['order'], $input['group_discussion'], $maxResults);
        } else {
            // general searching
            $results = $searcher->searchGeneral($input['keywords'], $constraints, $input['order'], $maxResults);
        }

        $search = $searchModel->insertSearch($results, $contentType, $input['keywords'], $constraints, $input['order'], $input['group_discussion']);

        return $search;
    }

    protected function _fetchResultsData(array $results)
    {
        // WARNING: only two legacy types (thread & post) use `data_limit` param
        $dataLimit = $this->_input->filterSingle('data_limit', XenForo_Input::UINT);
        if (empty($dataLimit) || empty($results)) {
            return array();
        }

        $dataResults = array_slice($results, 0, $dataLimit);

        $searchModel = $this->_getSearchModel();
        $contentData = $searchModel->prepareApiContentDataForSearch($dataResults);

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
