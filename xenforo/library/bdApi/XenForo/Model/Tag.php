<?php

class bdApi_XenForo_Model_Tag extends XFCP_bdApi_XenForo_Model_Tag
{
    public function bdApi_getTagsByIds(array $ids)
    {
        if (count($ids) === 1) {
            $tag = $this->getTagById(reset($ids));

            if (!empty($tag)) {
                return array($tag['tag_id'] => $tag);
            }
        } else {
            return $this->fetchAllKeyed('
                SELECT *
                FROM xf_tag
                WHERE tag_id IN (' . $this->_getDb()->quote($ids) . ')
            ', 'tag_id');
        }

        return array();
    }

    public function prepareApiDataForTags(array $tags)
    {
        $data = array();

        foreach ($tags as $tagId => $tag) {
            $data[strval($tagId)] = $tag['tag'];
        }

        // TODO: include tag data like content count / view count / etc.?

        return $data;
    }

}