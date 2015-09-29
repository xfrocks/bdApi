<?php

class bdApi_XenForo_ControllerPublic_Logout extends XFCP_bdApi_XenForo_ControllerPublic_Logout
{
    protected $_bdApi_redirect = null;

    public function actionIndex()
    {
        $userId = XenForo_Visitor::getUserId();

        $response = parent::actionIndex();

        if ($this->_bdApi_redirect !== null
            && $response instanceof XenForo_ControllerResponse_Redirect
            && $response->redirectTarget === $this->_bdApi_redirect
        ) {
            $this->_response->setHeader('X-Api-Logout-User', $userId);
        }

        return $response;
    }


    public function getDynamicRedirect($fallbackUrl = false, $useReferrer = true)
    {
        $input = $this->_input->filter(array(
            'redirect' => XenForo_Input::STRING,
            'timestamp' => XenForo_Input::UINT,
            'md5' => XenForo_Input::STRING,
        ));

        if (!empty($input['md5'])
            && !empty($input['timestamp'])
            && !empty($input['redirect'])
        ) {
            $md5 = '';
            try {
                $md5 = bdApi_Crypt::decryptTypeOne($input['md5'], $input['timestamp']);
            } catch (XenForo_Exception $e) {
                if (XenForo_Application::debugMode()) {
                    $this->_response->setHeader('X-Api-Exception', $e->getMessage());
                }
            }

            if (!empty($md5)
                && $md5 === md5($input['redirect'])
            ) {
                $this->_bdApi_redirect = $input['redirect'];
                return $input['redirect'];
            }
        }

        return parent::getDynamicRedirect($fallbackUrl, $useReferrer);
    }

}
