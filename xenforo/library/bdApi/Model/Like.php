<?php

class bdApi_Model_Like extends XenForo_Model
{
    public function getContentLikes($contentType, $contentId, array $fetchOptions = array())
    {
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->fetchAllKeyed($this->limitQueryResults(
            '
                SELECT liked_content.*
                FROM xf_liked_content AS liked_content
                WHERE liked_content.content_type = ?
                    AND liked_content.content_id = ?
                ORDER BY liked_content.like_date DESC
            ',
            $limitOptions['limit'],
            $limitOptions['offset']
        ), 'like_id', array($contentType, $contentId));
    }

    public function countContentLikes($contentType, $contentId)
    {
        return $this->_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM xf_liked_content AS liked_content
            WHERE liked_content.content_type = ?
                AND liked_content.content_id = ?
        ", array($contentType, $contentId));
    }
}
