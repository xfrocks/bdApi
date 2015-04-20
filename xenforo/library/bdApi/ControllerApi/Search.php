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
        $rawResults = $this->_doSearch();

        $results = array();
        foreach ($rawResults as $rawResult) {
            $results[] = array(
                'content_type' => $rawResult[0],
                'content_id' => $rawResult[1],
            );
        }

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
        $constraints = array();
        $rawResults = $this->_doSearch('thread', $constraints);

        $results = array();
        foreach ($rawResults as $rawResult) {
            $results[] = array(
                'content_type' => 'thread',
                'content_id' => $rawResult[1],

                // backward compatibility
                'thread_id' => $rawResult[1],
            );
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

        $rawResults = $this->_doSearch('post', $constraints);
        $threadIds = array();
        $results = array();
        foreach ($rawResults as $rawResult) {
            $results[] = array(
                'content_type' => $rawResult[0],
                'content_id' => $rawResult[1],

                // backward compatibility
                'post_id' => $rawResult[1],
            );

            if ($rawResult[0] == 'thread') {
                $threadIds[] = $rawResult[1];
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
        $constraints = array();
        $rawResults = $this->_doSearch('profile_post', $constraints);

        $results = array();
        foreach ($rawResults as $rawResult) {
            $results[] = array(
                'content_type' => 'profile_post',
                'content_id' => $rawResult[1],
            );
        }

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

        $filteredResults = array();
        foreach ($results as $result) {
            if ($this->_contentTypeIsSupported($result[0])) {
                $filteredResults[] = $result;
            }
        }

        return $filteredResults;
    }

    protected function _contentTypeIsSupported($contentType)
    {
        switch ($contentType) {
            case 'thread':
            case 'post':
            case 'profile_post':
                return true;
        }

        return false;
    }

    protected function _fetchResultsData(array $results)
    {
        $dataLimit = $this->_input->filterSingle('data_limit', XenForo_Input::UINT);
        if (empty($dataLimit) || empty($results)) {
            return array();
        }

        $dataResults = array_slice($results, 0, $dataLimit);
        return $this->_fetchContentData($dataResults);
    }

    protected function _fetchContentData(array $dataResults)
    {
        $threadIds = array();
        $postIds = array();
        $profilePostIds = array();
        $data = array();

        foreach ($dataResults as $key => $dataResult) {
            switch ($dataResult['content_type']) {
                case 'thread':
                    $threadIds[$dataResult['content_id']] = $key;
                    break;
                case 'post':
                    $postIds[$dataResult['content_id']] = $key;
                    break;
                case 'profile_post':
                    $profilePostIds[$dataResult['content_id']] = $key;
                    break;
            }
        }

        if (!empty($threadIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = $this->_request->getParams();
            $dataJobParams['thread_ids'] = implode(',', array_keys($threadIds));
            $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'threads', $dataJobParams);

            if (isset($dataJob['threads'])) {
                foreach ($dataJob['threads'] as $thread) {
                    if (!isset($threadIds[$thread['thread_id']])) {
                        // key not found?!
                        continue;
                    }
                    $key = $threadIds[$thread['thread_id']];

                    $data[$key] = $thread;
                    $data[$key]['content_type'] = 'thread';
                }
            }
        }

        if (!empty($postIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = $this->_request->getParams();
            $dataJobParams['post_ids'] = implode(',', array_keys($postIds));
            $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'posts', $dataJobParams);

            if (isset($dataJob['posts'])) {
                foreach ($dataJob['posts'] as $post) {
                    if (!isset($postIds[$post['post_id']])) {
                        // key not found?!
                        continue;
                    }
                    $key = $postIds[$post['post_id']];

                    $data[$key] = $post;
                    $data[$key]['content_type'] = 'post';
                }
            }
        }

        if (!empty($profilePostIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = $this->_request->getParams();
            $dataJobParams['profile_post_ids'] = implode(',', array_keys($profilePostIds));
            $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'profile-posts', $dataJobParams);

            if (isset($dataJob['profile_posts'])) {
                foreach ($dataJob['profile_posts'] as $profilePost) {
                    if (!isset($profilePostIds[$profilePost['profile_post_id']])) {
                        // key not found?!
                        continue;
                    }
                    $key = $profilePostIds[$profilePost['profile_post_id']];

                    $data[$key] = $profilePost;
                    $data[$key]['content_type'] = 'profile_post';
                }
            }
        }

        ksort($data);

        return array_values($data);
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
