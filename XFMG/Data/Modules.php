<?php

namespace Xfrocks\Api\XFMG\Data;

use Xfrocks\Api\Controller\AbstractController;

class Modules extends XFCP_Modules
{
    public function __construct()
    {
        parent::__construct();

        $this->addController(
            'Xfrocks\Api\XFMG\Controller\Media',
            'media',
            ':int<media_id>/'
        );
        $this->addController(
            'Xfrocks\Api\XFMG\Controller\Album',
            'media',
            'albums/:int<album_id>/',
            null,
            'albums'
        );

        $this->register('xfmg', 2018100101);
    }

    /**
     * @param AbstractController $controller
     * @return array
     */
    public function getDataForApiIndex($controller)
    {
        $data = parent::getDataForApiIndex($controller);

        $app = $controller->app();
        $apiRouter = $app->router('api');
        $data['links']['media'] = $apiRouter->buildLink('media');
        $data['links']['media/albums'] = $apiRouter->buildLink('media/albums');

        return $data;
    }
}

if (false) {
    // phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
    // phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
    class XFCP_Modules extends \Xfrocks\Api\Data\Modules
    {
    }
}
