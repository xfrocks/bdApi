<?php

namespace Xfrocks\Api\XF\ApiOnly\Template;

use Xfrocks\Api\Listener;

class Templater extends XFCP_Templater
{
    /**
     * @param string $username
     * @return array
     */
    public function getDefaultAvatarStylingForApi($username)
    {
        return $this->getDefaultAvatarStyling($username);
    }

    public function renderTemplate($template, array $params = [], $addDefaultParams = true)
    {
        $output = parent::renderTemplate($template, $params, $addDefaultParams);

        if ($template === 'public:bb_code_tag_attach' && isset($params['attachment'])) {
            /** @var \XF\Entity\Attachment $attachment */
            $attachment = $params['attachment'];
            if ($attachment->has_thumbnail) {
                $escape = false;
                $linkPublic = $this->escape($this->fnLinkType(
                    $this,
                    $escape,
                    'public',
                    'full:attachments',
                    $attachment,
                    ['hash' => $attachment->temp_hash]
                ));
                $linkApi = $this->escape($this->fnLinkType(
                    $this,
                    $escape,
                    Listener::$routerType,
                    'attachments',
                    $attachment,
                    ['hash' => $attachment->temp_hash]
                ));

                $output = str_replace($linkPublic, $linkApi, $output);

                $data = $attachment->Data;
                if ($data !== null) {
                    $output = self::_addDimensionsBySrc($output, "src=\"$linkApi\"", $data->height, $data->width);

                    $srcThumbnail = sprintf('src="%s"', $this->escape($attachment->thumbnail_url_full));
                    $output = self::_addDimensionsBySrc(
                        $output,
                        $srcThumbnail,
                        $data->thumbnail_height,
                        $data->thumbnail_width
                    );
                }
            }
        }

        return $output;
    }

    private static function _addDimensionsBySrc($html, $src, $height, $width)
    {
        if (substr_count($html, $src) !== 1) {
            return $html;
        }

        $html = str_replace($src, $src . " width=\"$width\"", $html);
        $html = str_replace($src, $src . " height=\"$height\"", $html);

        return $html;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Templater extends \XF\Template\Templater
    {
        // extension hint
    }
}
