<?php

namespace Xfrocks\Api\XFRM\Data;

use Xfrocks\Api\Controller\AbstractController;

class Modules extends XFCP_Modules
{
    public function __construct()
    {
        parent::__construct();

        $this->addController(
            'Xfrocks\Api\XFRM\Controller\Category',
            'resource-categories',
            ':int<resource_category_id>/'
        );
        $this->addController(
            'Xfrocks\Api\XFRM\Controller\ResourceItem',
            'resources',
            ':int<resource_id>/'
        );

        $this->register('resource', 2017040401);
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
        $data['links']['resource-categories'] = $apiRouter->buildLink('resource-categories');
        $data['links']['resources'] = $apiRouter->buildLink('resources');

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
