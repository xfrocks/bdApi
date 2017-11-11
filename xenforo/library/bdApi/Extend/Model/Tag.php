<?php

class bdApi_Extend_Model_Tag extends XFCP_bdApi_Extend_Model_Tag
{
    public function bdApi_getLatestTagId()
    {
        return $this->_getDb()->fetchOne('
            SELECT tag_id
            FROM xf_tag
            ORDER BY tag_id DESC
            LIMIT 1
        ');
    }

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

    public function bdApi_getTagsByIdRange($start, $end)
    {
        return $this->fetchAllKeyed('
            SELECT *
            FROM xf_tag
            WHERE tag_id BETWEEN ? AND ?
        ', 'tag_id', array($start, $end));
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

    public function prepareApiDataForTag(array $tag)
    {
        $publicKeys = array(
            // xf_tag
            'tag_id' => 'tag_id',
            'tag' => 'tag_text',
            'use_count' => 'tag_use_count',
        );

        $data = bdApi_Data_Helper_Core::filter($tag, $publicKeys);

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('tags', $tag),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('tags', $tag),
        );

        return $data;
    }
}
