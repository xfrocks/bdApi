<?php

namespace Xfrocks\Api\View\User;

use Xfrocks\Api\Util\Html;
use Xfrocks\Api\XF\ApiOnly\Image\DriverInterface;
use Xfrocks\Api\XF\ApiOnly\Template\Templater;

class DefaultAvatar extends \XF\Mvc\View
{
    /**
     * @return string
     */
    public function renderRaw()
    {
        $app = \XF::app();
        /** @var \XF\Entity\User $user */
        $user = $this->params['user'];

        /** @var Templater $templater */
        $templater = $app->templater();
        $defaultAvatarStyling = $templater->getDefaultAvatarStylingForApi($user->username);

        $manager = $app->imageManager();
        /** @var DriverInterface $image */
        $image = $manager->createImage($this->params['size'], $this->params['size']);

        $bgColor = Html::parseColor($defaultAvatarStyling['bgColor']);
        $image->setBackgroundColorForApi($bgColor[0], $bgColor[1], $bgColor[2]);

        $color = Html::parseColor($defaultAvatarStyling['color']);
        $font = Html::parseFontFamily($templater->func('property', ['avatarDynamicFont']));
        $percent = intval($templater->func('property', ['avatarDynamicTextPercent']));
        $text = $defaultAvatarStyling['innerContent'];

        $fontFile = $this->findTtfFontPath($font);
        if ($fontFile !== false) {
            $image->putTextAtCenterForApi($percent, $color[0], $color[1], $color[2], $fontFile, $text);
        }

        $this->response->contentType('image/png', '');
        $this->response->header('Cache-Control', 'public, max-age=31536000');

        return '' . $image->output(IMAGETYPE_PNG);
    }

    /**
     * @param string $font
     * @return string|false
     */
    protected function findTtfFontPath($font)
    {
        static $candidates = null;

        if ($candidates === null) {
            /**
             * Font installation guide for Debian:
             *
             * 1. Make sure contrib is enabled in /etc/apt/sources.list
             * 2. apt-get update
             * 3. apt-get install -y ttf-mscorefonts-installer
             */
            $candidates = [
                '/usr/share/fonts/truetype',
                '/usr/share/fonts/truetype/msttcorefonts',
                \XF::getRootDirectory() . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR . 'fonts',
            ];
        }

        foreach ($candidates as $candidate) {
            $fontFile = $candidate . DIRECTORY_SEPARATOR . $font . '.ttf';
            if (file_exists($fontFile)) {
                return $fontFile;
            }
        }

        return false;
    }
}
