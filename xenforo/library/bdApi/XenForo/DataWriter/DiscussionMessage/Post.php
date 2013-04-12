<?php
class bdApi_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdApi_XenForo_DataWriter_DiscussionMessage_Post
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xf_post']['bdapi_origin'] = array(
				'type' 			=> XenForo_DataWriter::TYPE_STRING,
				'maxLength' 	=> 255,
				'default' 		=> '',
		);

		return $fields;
	}
}