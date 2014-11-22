<?php

class bdApi_ControllerApi_OAuth extends bdApi_ControllerApi_Abstract
{
    public function actionGetAuthorize()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

        $authorizeParams = $oauth2Model->getServer()->getAuthorizeParams();
        $authorizeParams['social'] = $this->_input->filterSingle('social', XenForo_Input::STRING);

        $targetLink = XenForo_Link::buildPublicLink('account/authorize', array(), $authorizeParams);

        header('Location: ' . $targetLink);
        exit;
    }

    public function actionGetToken()
    {
        return $this->responseError(new XenForo_Phrase('bdapi_slash_oauth_token_only_accepts_post_requests'), 404);
    }

    public function actionPostToken()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

        // decrypt password for password grant type
        // we also need to recover the client secret for verification purpose
        $input = $this->_input->filter(array(
            'client_id' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,
        ));
        if (!empty($input['client_id']) AND !empty($input['password']) AND !empty($input['password_algo'])) {
            $client = $oauth2Model->getClientModel()->getClientById($input['client_id']);
            if (!empty($client)) {
                $password = bdApi_Crypt::decrypt($input['password'], $input['password_algo'], $client['client_secret']);
                $_POST['password'] = $password;
                $_POST['password_algo'] = '';
                $_POST['client_secret'] = $client['client_secret'];
            }
        }

        $oauth2Model->getServer()->grantAccessToken();

        // grantAccessToken will send output for us...
        exit;
    }

    protected function _getScopeForAction($action)
    {
        // no scope checking for this controller
        return false;
    }

}
