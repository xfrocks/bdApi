<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;

class Error extends AbstractController
{
    /**
     * @see \XF\Pub\Controller\Error::actionDispatchError
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error
     */
    public function actionDispatchError(ParameterBag $params)
    {
        if ($params['code'] === 'invalid_action'
            && substr($params['action'], 0, 3) === 'Get') {
            $controller = $this->app->controller($params['controller'], $this->request);
            $method = substr_replace($params['action'], 'actionPost', 0, 3);
            if (is_callable([$controller, $method])) {
                return $this->error(\XF::phrase('bdapi_only_accepts_post_requests'), 400);
            }
        }

        return $this->pluginError()->actionDispatchError($params);
    }

    /**
     * @see \XF\Pub\Controller\Error::actionException
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
     */
    public function actionException(ParameterBag $params)
    {
        return $this->pluginError()->actionException($params->get('exception', false));
    }

    /**
     * @see \XF\Pub\Controller\Error::actionAddOnUpgrade
     * @return \XF\Mvc\Reply\Error
     */
    public function actionAddOnUpgrade()
    {
        return $this->pluginError()->actionAddOnUpgrade();
    }

    public function assertIpNotBanned()
    {
        // no op
    }

    public function assertNotBanned()
    {
        // no op
    }

    public function assertViewingPermissions($action)
    {
        // no op
    }

    public function assertCorrectVersion($action)
    {
        // no op
    }

    public function assertBoardActive($action)
    {
        // no op
    }

    public function assertTfaRequirement($action)
    {
        // no op
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return null;
    }

    /**
     * @return \XF\ControllerPlugin\Error
     */
    protected function pluginError()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->plugin('XF:Error');
    }
}
