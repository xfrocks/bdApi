<?php

class bdApiConsumer_XenForo_Model_Avatar extends XFCP_bdApiConsumer_XenForo_Model_Avatar
{
    public function bdApiConsumer_applyAvatar($userId, $avatarUrl)
    {
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $dw->setExistingData($userId);
        $dw->bulkSet(array(
            'avatar_date' => 0,
            'avatar_width' => 0,
            'avatar_height' => 0,
            'avatar_crop_x' => 0,
            'avatar_crop_y' => 0,
            'gravatar' => bdApiConsumer_Helper_Avatar::getGravatar($userId, $avatarUrl),
        ), array('runVerificationCallback' => false));

        return $dw->save();
    }
}
