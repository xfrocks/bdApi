<?php

class bdApi_Extend_Model_Search extends XFCP_bdApi_Extend_Model_Search
{
    public function prepareApiDataForSearchResults(array $results)
    {
        $data = array();

        foreach ($results as $key => $result) {
            if ($this->checkApiSupportsContentType($result[XenForo_Model_Search::CONTENT_TYPE])) {
                $data[$key] = array(
                    'content_type' => $result[XenForo_Model_Search::CONTENT_TYPE],
                    'content_id' => $result[XenForo_Model_Search::CONTENT_ID],
                );

                if (count($result) > 2) {
                    foreach ($result as $_key => $_value) {
                        if ($_key === XenForo_Model_Search::CONTENT_TYPE) {
                            continue;
                        }
                        if ($_key === XenForo_Model_Search::CONTENT_ID) {
                            continue;
                        }

                        $data[$key][$_key] = $_value;
                    }
                }
            }
        }

        return $data;
    }

    public function checkApiSupportsContentType($contentType)
    {
        switch ($contentType) {
            case 'api_client_content':
            case 'thread':
            case 'post':
            case 'profile_post':
            case 'user':
                return true;
        }

        return false;
    }

    public function prepareApiContentDataForSearch(array $preparedResults)
    {
        $contentIdsByType = array();
        $data = array();

        foreach ($preparedResults as $key => $preparedResult) {
            $contentIdsByType[$preparedResult['content_type']][$preparedResult['content_id']] = $key;
        }

        /* @var $logModel bdApi_Model_Log */
        $logModel = $this->getModelFromCache('bdApi_Model_Log');
        $logModel->pauseLogging();

        foreach ($contentIdsByType as $contentType => $contentIds) {
            switch ($contentType) {
                case 'api_client_content':
                    $this->_prepareApiContentDataForSearch_doApiClientContents($contentIds, $preparedResults, $data);
                    break;
                case 'thread':
                    $this->_prepareApiContentDataForSearch_doThreads($contentIds, $preparedResults, $data);
                    break;
                case 'post':
                    $this->_prepareApiContentDataForSearch_doPosts($contentIds, $preparedResults, $data);
                    break;
                case 'profile_post':
                    $this->_prepareApiContentDataForSearch_doProfilePosts($contentIds, $preparedResults, $data);
                    break;
                case 'user':
                    $this->_prepareApiContentDataForSearch_doUsers($contentIds, $preparedResults, $data);
                    break;
                default:
                    $this->_prepareApiContentDataForSearch_doCustomContents(
                        $contentType,
                        $contentIds,
                        $preparedResults,
                        $data
                    );
            }
        }

        $logModel->resumeLogging();

        $sortedData = array();
        foreach (array_keys($preparedResults) as $key) {
            if (isset($data[$key])) {
                $sortedData[$key] = $data[$key];
                unset($data[$key]);
            }
        }
        unset($data);

        return $sortedData;
    }

    protected function _prepareApiContentDataForSearch_doApiClientContents(
        array $clientContentIds,
        array $preparedResults,
        array &$data
    ) {
        if (empty($clientContentIds)) {
            return;
        }

        /** @var bdApi_Model_ClientContent $clientContentModel */
        $clientContentModel = $this->getModelFromCache('bdApi_Model_ClientContent');
        $clientContents = $clientContentModel->getClientContents(array(
            'client_content_id' => array_keys($clientContentIds),
        ));

        foreach ($clientContents as $clientContentId => $clientContent) {
            if (!isset($clientContentIds[$clientContentId])) {
                // key not found?!
                continue;
            }

            $key = $clientContentIds[$clientContentId];
            $data[$key] = array_merge(
                $preparedResults[$key],
                array_intersect_key(
                    $clientContent,
                    array_flip(array(
                        'title',
                        'body',
                        'date',
                        'link',
                        'user_id',
                    ))
                )
            );
        }
    }

    protected function _prepareApiContentDataForSearch_doThreads(array $threadIds, array $preparedResults, array &$data)
    {
        if (empty($threadIds)) {
            return;
        }

        $dataJobParams = array();
        $dataJobParams['thread_ids'] = implode(',', array_keys($threadIds));
        $dataJobParams['fields_filter_prefix'] = 'content.';
        $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'threads', $dataJobParams);

        if (isset($dataJob['_job_response'])
            && !empty($dataJob['_job_response']->params['threads'])
        ) {
            foreach ($dataJob['_job_response']->params['threads'] as $thread) {
                if (empty($thread['thread_id'])
                    || !isset($threadIds[$thread['thread_id']])
                ) {
                    // key not found?!
                    continue;
                }

                $key = $threadIds[$thread['thread_id']];
                $data[$key] = array_merge($preparedResults[$key], $thread);
            }
        }
    }

    protected function _prepareApiContentDataForSearch_doPosts(array $postIds, array $preparedResults, array &$data)
    {
        if (empty($postIds)) {
            return;
        }

        $dataJobParams = array();
        $dataJobParams['post_ids'] = implode(',', array_keys($postIds));
        $dataJobParams['fields_filter_prefix'] = 'content.';
        $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'posts', $dataJobParams);

        if (isset($dataJob['_job_response'])
            && !empty($dataJob['_job_response']->params['posts'])
        ) {
            foreach ($dataJob['_job_response']->params['posts'] as $post) {
                if (empty($post['post_id'])
                    || !isset($postIds[$post['post_id']])
                ) {
                    // key not found?!
                    continue;
                }

                $key = $postIds[$post['post_id']];
                if (empty($post['post_is_first_post'])
                    || empty($post['thread'])
                    || empty($post['thread']['thread_id'])
                ) {
                    // the found post is a reply
                    $data[$key] = array_merge($preparedResults[$key], $post);
                } else {
                    // the found post is a first post, return as thread instead
                    $thread = $post['thread'];
                    unset($post['thread']);
                    $thread['first_post'] = $post;

                    $data[$key] = array_merge(
                        $preparedResults[$key],
                        array(
                            'content_type' => 'thread',
                            'content_id' => $thread['thread_id'],
                            'search_result_content_type' => 'post',
                            'search_result_content_id' => $post['post_id'],
                        ),
                        $thread
                    );
                }
            }
        }
    }

    protected function _prepareApiContentDataForSearch_doProfilePosts(
        array $profilePostIds,
        array $preparedResults,
        array &$data
    ) {
        if (empty($profilePostIds)) {
            return;
        }

        $dataJobParams = array();
        $dataJobParams['profile_post_ids'] = implode(',', array_keys($profilePostIds));
        $dataJobParams['fields_filter_prefix'] = 'content.';
        $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'profile-posts', $dataJobParams);

        if (isset($dataJob['_job_response'])
            && !empty($dataJob['_job_response']->params['profile_posts'])
        ) {
            foreach ($dataJob['_job_response']->params['profile_posts'] as $profilePost) {
                if (empty($profilePost['profile_post_id'])
                    || !isset($profilePostIds[$profilePost['profile_post_id']])
                ) {
                    // key not found?!
                    continue;
                }

                $key = $profilePostIds[$profilePost['profile_post_id']];
                $data[$key] = array_merge($preparedResults[$key], $profilePost);
            }
        }
    }

    protected function _prepareApiContentDataForSearch_doUsers(array $userIds, array $preparedResults, array &$data)
    {
        if (empty($userIds)) {
            return;
        }

        $dataJobParams = array();
        $dataJobParams['user_ids'] = implode(',', array_keys($userIds));
        $dataJobParams['fields_filter_prefix'] = 'content.';
        $dataJob = bdApi_Data_Helper_Batch::doJob('GET', 'users', $dataJobParams);

        if (isset($dataJob['_job_response'])
            && !empty($dataJob['_job_response']->params['users'])
        ) {
            foreach ($dataJob['_job_response']->params['users'] as $user) {
                if (empty($user['user_id'])
                    || !isset($userIds[$user['user_id']])
                ) {
                    // key not found?!
                    continue;
                }

                $key = $userIds[$user['user_id']];
                $data[$key] = array_merge($preparedResults[$key], $user);
            }
        }
    }

    protected function _prepareApiContentDataForSearch_doCustomContents(
        $contentType,
        array $contentIds,
        array $preparedResults,
        array &$data
    ) {
        return;
    }
}
