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
                return true;
        }

        return false;
    }

    public function prepareApiContentDataForSearch(array $preparedResults)
    {
        $clientContentIds = array();
        $threadIds = array();
        $postIds = array();
        $profilePostIds = array();
        $data = array();

        foreach ($preparedResults as $key => $preparedResult) {
            switch ($preparedResult['content_type']) {
                case 'api_client_content':
                    $clientContentIds[$preparedResult['content_id']] = $key;
                    break;
                case 'thread':
                    $threadIds[$preparedResult['content_id']] = $key;
                    break;
                case 'post':
                    $postIds[$preparedResult['content_id']] = $key;
                    break;
                case 'profile_post':
                    $profilePostIds[$preparedResult['content_id']] = $key;
                    break;
            }
        }

        if (!empty($clientContentIds)) {
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
                $data[$key] = array_merge($preparedResults[$key],
                    array_intersect_key($clientContent,
                        array_flip(array(
                                'title',
                                'body',
                                'date',
                                'link',
                                'user_id',
                            )
                        )
                    )
                );
            }
        }

        if (!empty($threadIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = array();
            $dataJobParams['thread_ids'] = implode(',', array_keys($threadIds));
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

        if (!empty($postIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = array();
            $dataJobParams['post_ids'] = implode(',', array_keys($postIds));
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
                    if (!empty($post['thread']['first_post'])) {
                        // the found post is a reply
                        $data[$key] = array_merge($preparedResults[$key], $post);
                    } else {
                        // the found post is a first post, return as thread instead
                        $thread = $post['thread'];
                        unset($post['thread']);
                        $thread['first_post'] = $post;

                        $data[$key] = array_merge(array(
                            'content_type' => 'thread',
                            'content_id' => $thread['thread_id'],
                        ), $thread);
                    }
                }
            }
        }

        if (!empty($profilePostIds)) {
            // fetch the first few thread data as a bonus
            $dataJobParams = array();
            $dataJobParams['profile_post_ids'] = implode(',', array_keys($profilePostIds));
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

        ksort($data);

        return $data;
    }
}