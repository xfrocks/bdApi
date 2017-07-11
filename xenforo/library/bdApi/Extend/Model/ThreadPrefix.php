<?php

class bdApi_Extend_Model_ThreadPrefix extends XFCP_bdApi_Extend_Model_ThreadPrefix
{
    public function bdApi_getUsablePrefixesByForums($nodeIds, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $prefixes = $this->getPrefixesInForums($nodeIds);

        $prefixesByForum = array();
        foreach ($prefixes AS $prefix) {
            if (!$this->_verifyPrefixIsUsableInternal($prefix, $viewingUser)) {
                continue;
            }

            $prefixId = $prefix['prefix_id'];
            $forumId = $prefix['node_id'];
            $prefixGroupId = $prefix['prefix_group_id'];

            if (!isset($prefixesByForum[$forumId])) {
                $prefixesByForum[$forumId] = array();
            }

            if (!isset($prefixesByForum[$forumId][$prefixGroupId])) {
                $prefixesByForum[$forumId][$prefixGroupId] = array();
            }

            $prefixesByForum[$forumId][$prefixGroupId]['prefixes'][$prefixId] = $prefix;
        }

        return $prefixesByForum;
    }

    public function prepareApiDataForPrefixes(array $prefixes, array $prefixIds = null)
    {
        $data = array();

        foreach ($prefixes as $prefixId => $prefix) {
            if (isset($prefix['prefix_id'])) {
                // this is a prefix
                if (is_array($prefixIds)
                    && !in_array($prefixId, $prefixIds)
                ) {
                    continue;
                }

                $data[] = $this->prepareApiDataForPrefix($prefix);
            } elseif (isset($prefix['prefixes'])) {
                // this is a group
                $groupPrefixes = $this->prepareApiDataForPrefixes($prefix['prefixes'], $prefixIds);
                if (count($groupPrefixes) === 0) {
                    continue;
                }

                $data[] = array(
                    'group_title' => $prefixId > 0
                        ? new XenForo_Phrase($this->getPrefixGroupTitlePhraseName($prefixId))
                        : new XenForo_Phrase('ungrouped'),
                    'group_prefixes' => $groupPrefixes,
                );
            }
        }

        return $data;
    }

    public function prepareApiDataForPrefix(array $prefix)
    {
        $publicKeys = array(
            // xf_thread_prefix
            'prefix_id' => 'prefix_id',
        );

        $data = bdApi_Data_Helper_Core::filter($prefix, $publicKeys);

        $data['prefix_title'] = new XenForo_Phrase($this->getPrefixTitlePhraseName($prefix['prefix_id']));

        return $data;
    }
}
