<?php

class bdApi_Model_Like extends XenForo_Model
{
    public function getContentLikes($contentType, $contentId, array $fetchOptions = array())
    {
        return $this->getAllLikes(array(
            'content_type' => $contentType,
            'content_id' => $contentId
        ), $fetchOptions);
    }

    public function countContentLikes($contentType, $contentId)
    {
        return $this->countLikes(array(
            'content_type' => $contentType,
            'content_id' => $contentId
        ));
    }

    public function getAllLikes(array $conditions = array(), array $fetchOptions = array())
    {
        $whereClause = $this->prepareLikeConditions($conditions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->fetchAllKeyed($this->limitQueryResults(
            '
                SELECT liked_content.*
                FROM xf_liked_content AS liked_content
                WHERE ' . $whereClause . '
                ORDER BY liked_content.like_date DESC
            ',
            $limitOptions['limit'],
            $limitOptions['offset']
        ), 'like_id');
    }

    public function countLikes(array $conditions)
    {
        $whereClause = $this->prepareLikeConditions($conditions);

        return $this->_getDb()->fetchOne('
            SELECT COUNT(*)
            FROM xf_liked_content AS liked_content
            WHERE ' . $whereClause . '
        ');
    }

    public function prepareLikeConditions(array $conditions)
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['content_type'])) {
            if (is_array($conditions['content_type'])) {
                $sqlConditions[] = 'liked_content.content_type IN (' . $db->quote($conditions['content_type']) . ')';
            } else {
                $sqlConditions[] = 'liked_content.content_type = ' . $db->quote($conditions['content_type']);
            }
        }

        if (isset($conditions['content_id'])) {
            if (is_array($conditions['content_id'])) {
                $sqlConditions[] = 'liked_content.content_id IN (' . $db->quote($conditions['content_id']) . ')';
            } else {
                $sqlConditions[] = 'liked_content.content_id = ' . $db->quote($conditions['content_id']);
            }
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareApiDataForLikes(array $likes)
    {
        $data = array();
        foreach ($likes as $like) {
            $data[] = $this->prepareApiDataForLike($like);
        }

        return $data;
    }

    public function prepareApiDataForLike(array $like)
    {
        return [
            XenForo_Model_Search::CONTENT_TYPE => 'user',
            XenForo_Model_Search::CONTENT_ID => $like['like_user_id'],
            'like_date' => $like['like_date'],
        ];
    }
}
