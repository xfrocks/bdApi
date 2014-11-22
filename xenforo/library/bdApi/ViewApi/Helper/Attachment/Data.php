<?php

class bdApi_ViewApi_Helper_Attachment_Data extends bdApi_ViewApi_Base
{
    public function renderRaw()
    {
        $attachment = $this->_params['attachment'];
        $attachmentFile = $this->_params['attachmentFile'];
        $attachmentFileSize = $attachment['file_size'];

        $extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
        $imageTypes = array(
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png'
        );

        if (in_array($extension, array_keys($imageTypes))) {
            $this->_response->setHeader('Content-type', $imageTypes[$extension], true);
            $this->setDownloadFileName($attachment['filename'], true);

            $resize = $this->_params['resize'];
            switch ($extension) {
                case 'gif':
                    $imageType = IMAGETYPE_GIF;
                    break;
                case 'jpg':
                case 'jpeg':
                case 'jpe':
                    $imageType = IMAGETYPE_JPEG;
                    break;
                case 'png':
                    $imageType = IMAGETYPE_PNG;
                    break;
            }

            if ((!empty($resize['max_width']) OR !empty($resize['max_height'])) AND !empty($imageType)) {
                // start resizing...
                $image = XenForo_Image_Abstract::createFromFile($attachmentFile, $imageType);
                if (empty($image)) {
                    throw new XenForo_Exception('Unable to read attachment as image');
                }

                $tempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
                if (empty($tempFile)) {
                    throw new XenForo_Exception('Unable to create temp file to resize attachment');
                }

                if (!empty($resize['keep_ratio'])) {
                    $image->thumbnail($resize['max_width'], $resize['max_height']);
                } elseif (!empty($resize['max_width']) AND !empty($resize['max_height'])) {
                    $image->thumbnailFixedShorterSide(max($resize['max_width'], $resize['max_height']));

                    if ($image->getWidth() >= $resize['max_width'] AND $image->getHeight() >= $resize['max_height']) {
                        $x = ($image->getWidth() - $resize['max_width']) / 2;
                        $y = ($image->getHeight() - $resize['max_height']) / 2;
                        $image->crop($x, $y, $resize['max_width'], $resize['max_height']);
                    }
                }

                $image->output($imageType, $tempFile);
                $attachmentFile = $tempFile;
                $attachmentFileSize = filesize($tempFile);

                unset($image);
            }
        } else {
            $this->_response->setHeader('Content-type', 'application/octet-stream', true);
            $this->setDownloadFileName($attachment['filename']);
        }

        $this->_response->setHeader('ETag', $attachment['attach_date'], true);
        $this->_response->setHeader('Content-Length', $attachmentFileSize, true);
        $this->_response->setHeader('X-Content-Type-Options', 'nosniff');

        return new XenForo_FileOutput($attachmentFile);
    }

}
