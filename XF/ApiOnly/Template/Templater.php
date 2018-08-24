<?php

namespace Xfrocks\Api\XF\ApiOnly\Template;

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
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Templater extends \XF\Template\Templater
    {
        // extension hint
    }
}
