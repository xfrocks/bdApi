<?php

namespace Xfrocks\Api\XFMG\Data;

use Xfrocks\Api\Controller\AbstractController;

class Modules extends XFCP_Modules
{
    public function __construct()
    {
        parent::__construct();

        $this->addController(
            'Xfrocks\Api\XFMG\Controller\Album',
            'albums',
            ':int<album_id>/'
        );

        $this->register('album', 2018100101);
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
        $data['links']['albums'] = $apiRouter->buildLink('albums');

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
