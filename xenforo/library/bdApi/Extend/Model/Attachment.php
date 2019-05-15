<?php

class bdApi_Extend_Model_Attachment extends XFCP_bdApi_Extend_Model_Attachment
{
    public function prepareApiDataForAttachment(array $attachment)
    {
        $attachment = $this->prepareAttachment($attachment);

        $publicKeys = array(
            // xf_attachment
            'attachment_id' => 'attachment_id',
            'view_count' => 'attachment_download_count',
            // xf_attachment_data
            'filename' => 'filename',
        );

        $data = bdApi_Data_Helper_Core::filter($attachment, $publicKeys);

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('attachments', $attachment),
            'data' => bdApi_Data_Helper_Core::safeBuildApiLink('attachments/data', $attachment),
        );

        if (!empty($attachment['thumbnailUrl'])) {
            if (!empty($attachment['width']) && !empty($attachment['height'])) {
                $data['attachment_width'] = $attachment['width'];
                $data['attachment_height'] = $attachment['height'];
            }

            $data['links']['thumbnail'] = XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl']);
        }

        return $data;
    }
}
