<?php

class bdApi_ControllerHelper_Delete extends XenForo_ControllerHelper_Abstract
{
    public function filterReason()
    {
        $defaultReason = '';

        /** @var bdApi_Session $session */
        $session = XenForo_Application::getSession();
        $client = $session->getOAuthClient();
        if (!empty($client['name'])) {
            $defaultReason = $client['name'];
        }

        return $this->_controller->getInput()->filterSingle(
            'reason',
            XenForo_Input::STRING,
            array('default' => $defaultReason)
        );
    }
}
