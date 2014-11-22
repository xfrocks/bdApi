<?php

class bdApi_XenForo_ControllerPublic_Error extends XFCP_bdApi_XenForo_ControllerPublic_Error
{
    public function actionAuthorizeGuest()
    {
        $requestPaths = XenForo_Application::get('requestPaths');
        $social = $this->_input->filterSingle('social', XenForo_Input::STRING);
        switch ($social) {
            case 'facebook':
                $facebookLink = XenForo_Link::buildPublicLink('full:register/facebook', null, array(
                    'reg' => 1,
                    'redirect' => $requestPaths['fullUri'],
                ));
                return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $facebookLink);
            case 'twitter':
                $twitterLink = XenForo_Link::buildPublicLink('full:register/twitter', null, array(
                    'reg' => 1,
                    'redirect' => $requestPaths['fullUri'],
                ));
                return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $twitterLink);
        }

        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

        /* @var $clientModel bdApi_Model_Client */
        $clientModel = $oauth2Model->getClientModel();

        $authorizeParams = $oauth2Model->getServer()->getAuthorizeParams();

        $client = $clientModel->getClientById($authorizeParams['client_id']);
        if (empty($client)) {
            throw new XenForo_Exception(new XenForo_Phrase('bdapi_authorize_error_client_x_not_found', array('client' => $authorizeParams['client_id'])));
        }

        $viewParams = array(
            'client' => $client,
            'social' => $social,
        );

        $view = $this->responseView('bdApi_ViewPublic_Error_AuthorizeGuest', 'bdapi_error_authorize_guest', $viewParams);
        $view->responseCode = 403;

        return $view;
    }

}
