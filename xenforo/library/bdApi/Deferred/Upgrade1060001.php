<?php

class bdApi_Deferred_Upgrade1060001 extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        bdApi_Data_ColumnOption::updateIfExists(
            'bdApi_trackPostOrigin',
            'xf_post',
            'bdapi_origin'
        );
    }
}
