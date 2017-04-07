<?php

class bdApi_ControllerApi_Search extends bdApi_ControllerApi_Abstract
{
    const OPTION_NO_KEYWORDS = 'noKeywords';
    const OPTION_ORDER = 'order';
    const OPTION_SEARCH_TYPE = 'searchType';
    const OPTION_SEARCH_TYPE_TAGGED = 'tagged';
    const OPTION_SEARCH_TYPE_USER_TIMELINE = 'userTimeline';

    public function actionGetIndex()
    {
        $data = array('links' => array(
            'posts' => bdApi_Data_Helper_Core::safeBuildApiLink('search/posts'),
            'threads' => bdApi_Data_Helper_Core::safeBuildApiLink('search/threads'),
            'profile-posts' => bdApi_Data_Helper_Core::safeBuildApiLink('search/profile-posts'),
        ));

        if (XenForo_Application::$versionId > 1050000) {
            $data['links']['tagged'] = bdApi_Data_Helper_Core::safeBuildApiLink('search/tagged');
        }

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
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $search = $this->_getSearchModel()->prepareSearch($search);
        $pageResultIds = $this->_getSearchModel()->sliceSearchResultsToPage($search, $page, $limit);
        $results = $this->_getSearchModel()->prepareApiDataForSearchResults($pageResultIds);
        if (!$this->_isFieldExcluded('content')) {
            $contentData = $this->_getSearchModel()->prepareApiContentDataForSearch($results);
        } else {
            $contentData = $results;
        }

        $data = array(
            '_search' => $search,
            'data' => array_values($contentData),
            'data_total' => $search['result_count'],
        );

        if (XenForo_Application::$versionId > 1050000
            && !empty($search['searchConstraints']['tag'])
        ) {
            /** @var bdApi_Extend_Model_Tag $tagModel */
            $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
            $tags = $tagModel->bdApi_getTagsByIds(explode(' ', $search['searchConstraints']['tag']));

            $data['search_tags'] = $tagModel->prepareApiDataForTags($tags);
        }

        switch ($search['search_type']) {
            case self::OPTION_SEARCH_TYPE_USER_TIMELINE:
                foreach ($data['data'] as &$profilePostDataRef) {
                    if (isset($profilePostDataRef['timeline_user'])) {
                        unset($profilePostDataRef['timeline_user']);
                    }
                }

                if (!$this->_isFieldExcluded('user')
                    && !empty($search['searchConstraints']['user'])
                    && is_array($search['searchConstraints']['user'])
                    && count($search['searchConstraints']['user']) === 1
                ) {
                    $userId = reset($search['searchConstraints']['user']);

                    /** @var bdApi_Extend_Model_User $userModel */
                    $userModel = $this->getModelFromCache('XenForo_Model_User');
                    $user = $userModel->getUserById($userId, $userModel->getFetchOptionsToPrepareApiData());
                    $data['user'] = $this->_filterDataSingle($userModel->prepareApiDataForUser($user), array('user'));
                }
                break;
        }

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $search['result_count'], $page, 'search/results', $search, $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Search_Results', $data);
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
            $data['data'] = $resultsData['data'];

            bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data,
                $resultsData['limit'], $search['result_count'], $resultsData['page'],
                'search/results', $search, $resultsData['pageLinkParams']);
        }

        return $this->responseData('bdApi_ViewApi_Search_Threads', $data);
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
            $data['data'] = $resultsData['data'];

            bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data,
                $resultsData['limit'], $search['result_count'], $resultsData['page'],
                'search/results', $search, $resultsData['pageLinkParams']);
        }

        return $this->responseData('bdApi_ViewApi_Search_Posts', $data);
    }

    public function actionPostProfilePosts()
    {
        $search = $this->_doSearch('profile_post');

        $this->_request->setParam('search_id', $search['search_id']);
        return $this->responseReroute(__CLASS__, 'get-results');
    }

    public function actionPostTagged()
    {
        if (XenForo_Application::$versionId < 1050000) {
            return $this->responseNoPermission();
        }

        $tagText = $this->_input->filterSingle('tag', XenForo_Input::STRING);
        if (empty($tagText)) {
            return $this->responseError(new XenForo_Phrase('requested_tag_not_found'), 404);
        }

        /** @var XenForo_Model_Tag $tagModel */
        $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
        $tag = $tagModel->getTag($tagText);
        if (empty($tag)) {
            return $this->responseError(new XenForo_Phrase('requested_tag_not_found'), 404);
        }

        $limit = XenForo_Application::getOptions()->get('maximumSearchResults');
        $contentTags = $tagModel->getContentIdsByTagId($tag['tag_id'], $limit);

        $search = $this->_getSearchModel()->insertSearch($contentTags, self::OPTION_SEARCH_TYPE_TAGGED, '', array(), 'date', false);

        $this->_request->setParam('search_id', $search['search_id']);
        return $this->responseReroute(__CLASS__, 'get-results');
    }

    public function actionUserTimeline()
    {
        $search = $this->_doSearch('', array(), array(
            self::OPTION_NO_KEYWORDS => true,
            self::OPTION_ORDER => 'date',
            self::OPTION_SEARCH_TYPE => self::OPTION_SEARCH_TYPE_USER_TIMELINE,
        ));

        $this->_request->setParam('search_id', $search['search_id']);
        return $this->responseReroute(__CLASS__, 'get-results');
    }

    public function actionPostIndexing()
    {
        $input = $this->_input->filter(array(
            'content_type' => XenForo_Input::STRING,
            'content_id' => XenForo_Input::UINT,
            'title' => XenForo_Input::STRING,
            'body' => XenForo_Input::STRING,
            'date' => array(XenForo_Input::UINT, 'default' => XenForo_Application::$time),
            'link' => XenForo_Input::STRING,
            'extra_data' => XenForo_Input::ARRAY_SIMPLE,
        ));

        $session = bdApi_Data_Helper_Core::safeGetSession();
        if (!$session->getOAuthClientOption('allow_search_indexing')) {
            return $this->responseNoPermission();
        }

        $dbKeys = array(
            'client_id' => $session->getOAuthClientId(),
            'content_type' => $input['content_type'],
            'content_id' => $input['content_id'],
        );
        /** @var bdApi_Model_ClientContent $clientContentModel */
        $clientContentModel = $this->getModelFromCache('bdApi_Model_ClientContent');
        $existingContents = $clientContentModel->getClientContents($dbKeys);
        $existingContent = null;
        if (!empty($existingContents)) {
            $existingContent = reset($existingContents);
        }

        $dw = XenForo_DataWriter::create('bdApi_DataWriter_ClientContent');
        if (!empty($existingContent)) {
            $dw->setExistingData($existingContent, true);
            $input['extra_data'] = array_merge($existingContent['extraData'], $input['extra_data']);
        } else {
            $dw->bulkSet($dbKeys);
        }

        $dw->set('title', $input['title']);
        $dw->set('body', $input['body']);
        $dw->set('date', $input['date']);
        $dw->set('link', $input['link']);
        $dw->set('extra_data', $input['extra_data']);

        if ($dw->isInsert()
            || XenForo_Visitor::getUserId() > 0
        ) {
            $dw->set('user_id', XenForo_Visitor::getUserId());
        }

        $dw->preSave();

        if ($dw->hasErrors()) {
            return $this->responseErrors($dw->getErrors(), 400);
        }

        $dw->save();

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function _doSearch($contentType = '', array $constraints = array(), array $options = array())
    {
        $tagText = $this->_input->filterSingle('tag', XenForo_Input::STRING);
        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

        if (!XenForo_Visitor::getInstance()->canSearch()) {
            throw $this->getNoPermissionResponseException();
        }

        $input = array(
            'keywords' => '',
            'order' => 'relevance',
            'group_discussion' => false,
        );

        if (empty($options[self::OPTION_NO_KEYWORDS])) {
            $input['keywords'] = $this->_input->filterSingle('q', XenForo_Input::STRING);
            $input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, '');
            // don't allow searching of censored stuff
            if (empty($input['keywords'])) {
                throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_slash_search_requires_q'), 400));
            }
        }

        if (!empty($options[self::OPTION_ORDER])) {
            $input['order'] = $options[self::OPTION_ORDER];
        }

        if (!empty($tagText)) {
            /** @var XenForo_Model_Tag $tagModel */
            $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
            $tag = $tagModel->getTag($tagText);
            if (empty($tag)) {
                throw $this->responseException($this->responseError(new XenForo_Phrase('requested_tag_not_found'), 404));
            }

            $constraints['tag'] = $tag['tag_id'];
        }

        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');

        switch ($contentType) {
            case 'thread':
            case 'post':
                // only these two legacy content types support `limit` param while searching
                // others use `limit` to control how many pieces of data are returned
                list($limit,) = $this->filterLimitAndPage();
                if ($limit > 0) {
                    $maxResults = min($maxResults, $limit);
                }
        }

        if (!empty($forumId)) {
            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forumId)));
            $nodeIds = array_unique(array_merge(array($forumId), $childNodeIds));
            $constraints['node'] = implode(' ', $nodeIds);
            if (!$constraints['node']) {
                unset($constraints['node']);
            }
        }

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

        $searchType = $contentType;
        if (!empty($options[self::OPTION_SEARCH_TYPE])) {
            $searchType = $options[self::OPTION_SEARCH_TYPE];
        }

        $search = $searchModel->insertSearch($results, $searchType, $input['keywords'], $constraints, $input['order'], $input['group_discussion']);

        return $search;
    }

    protected function _fetchResultsData(array $results)
    {
        if (empty($results)) {
            return $results;
        }

        // WARNING: only two legacy types (thread & post) use `data_limit` param
        list($dataLimit,) = $this->filterLimitAndPage($tempArray, 'data_limit');
        if (empty($dataLimit)) {
            return array();
        }

        $dataResults = array_slice($results, 0, $dataLimit);

        $searchModel = $this->_getSearchModel();
        $contentData = $searchModel->prepareApiContentDataForSearch($dataResults);

        return array(
            'data' => array_values($contentData),
            'limit' => $dataLimit,
            'page' => 1,
            'pageLinkParams' => array('limit' => $dataLimit),
        );
    }

    /**
     * @return bdApi_Extend_Model_Search
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

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_Search';
    }
}
