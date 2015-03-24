<?php

class bdApi_ControllerApi_Search extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $data = array('links' => array(
            'posts' => XenForo_Link::buildApiLink('search/posts'),
            'threads' => XenForo_Link::buildApiLink('search/threads'),
        ));

        return $this->responseData('bdApi_ViewApi_Index', $data);
    }

    public function actionGetThreads()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
    }

    public function actionPostThreads()
    {
        $dataLimit = $this->_input->filterSingle('data_limit', XenForo_Input::UINT);
        $threadIds = array();

        $constraints = array();
        $rawResults = $this->_doSearch('thread', $constraints);

        $results = array();
        foreach ($rawResults as $rawResult) {
            $results[] = array('thread_id' => $rawResult[1]);

            if ($dataLimit > 0 && count($threadIds) < $dataLimit) {
                $threadIds[] = $rawResult[1];
            }
        }

        $data = array('threads' => $results);

        if (!empty($threadIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = $this->_request->getParams();
            $dataJobParams['thread_ids'] = implode(',', $threadIds);
            $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'threads', $dataJobParams);

            if (isset($dataJob['threads'])) {
                $data['data'] = $dataJob['threads'];
            }
        }

        return $this->responseData('bdApi_ViewApi_Search_Threads', $data);
    }

    public function actionGetPosts()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
    }

    public function actionPostPosts()
    {
        $dataLimit = $this->_input->filterSingle('data_limit', XenForo_Input::UINT);
        $postIds = array();

        $constraints = array();
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        if (!empty($threadId)) {
            $constraints['thread'] = $threadId;
        }

        $this->_doSearch('post', $constraints);

        // perform get posts from model because the search result are grouped
        $this->_getPostModel();
        $posts = bdApi_XenForo_Model_Post::bdApi_getCachedPosts();

        $results = array();
        foreach ($posts as $post) {
            $results[] = array('post_id' => $post['post_id']);

            if ($dataLimit > 0 && count($postIds) < $dataLimit) {
                $postIds[] = $post['post_id'];
            }
        }

        $data = array('posts' => $results);

        if (!empty($postIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = $this->_request->getParams();
            $dataJobParams['post_ids'] = implode(',', $postIds);
            $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'posts', $dataJobParams);

            if (isset($dataJob['posts'])) {
                $data['data'] = $dataJob['posts'];
            }
        }

        return $this->responseData('bdApi_ViewApi_Search_Posts', $data);
    }

    public function _doSearch($contentType, array $constraints = array())
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
                // just 0
            }
        }

        $searchModel = $this->_getSearchModel();
        $typeHandler = $searchModel->getSearchDataHandler($contentType);
        $searcher = new XenForo_Search_Searcher($searchModel);

        return $searcher->searchType($typeHandler, $input['keywords'], $constraints, 'relevance', false, $maxResults);
    }

    /**
     * @return XenForo_Model_Search
     */
    protected function _getSearchModel()
    {
        return $this->getModelFromCache('XenForo_Model_Search');
    }

    /**
     * @return XenForo_Model_Post
     */
    protected function _getPostModel()
    {
        return $this->getModelFromCache('XenForo_Model_Post');
    }

    /**
     * @return XenForo_Model_Thread
     */
    protected function _getThreadModel()
    {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }

    /**
     * @return XenForo_Model_Forum
     */
    protected function _getForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Node');
    }
}
