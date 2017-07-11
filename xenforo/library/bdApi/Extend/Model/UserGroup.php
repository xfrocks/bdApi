<?php

class bdApi_Extend_Model_UserGroup extends XFCP_bdApi_Extend_Model_UserGroup
{
    public function bdApi_getAllUserGroupsCached()
    {
        static $userGroups = null;

        if ($userGroups === null) {
            $userGroups = $this->getAllUserGroups();
        }

        return $userGroups;
    }

    public function prepareApiDataForUserGroups(array $userGroups)
    {
        $data = array();

        foreach ($userGroups as $key => $userGroup) {
            $data[] = $this->prepareApiDataForUserGroup($userGroup);
        }

        return $data;
    }

    public function prepareApiDataForUserGroup(array $userGroup)
    {
        $publicKeys = array(
            // xf_user_group
            'user_group_id' => 'user_group_id',
            'title' => 'user_group_title',
        );

        $data = bdApi_Data_Helper_Core::filter($userGroup, $publicKeys);

        return $data;
    }
}
