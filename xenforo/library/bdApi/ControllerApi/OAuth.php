<?php

class bdApi_ControllerApi_OAuth extends bdApi_ControllerApi_Abstract
{
    public function actionGetAuthorize()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

        $authorizeParams = $this->_input->filter($oauth2Model->getAuthorizeParamsInputFilter());

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

        return $oauth2Model->getServer()->actionOauthToken($this);
    }

    public function actionPostTokenFacebook()
    {
        $client = $this->_getClientOrError();

        /* @var $userModel XenForo_Model_User */
        $userModel = $this->getModelFromCache('XenForo_Model_User');
        /* @var $userExternalModel XenForo_Model_UserExternal */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

        $facebookToken = $this->_input->filterSingle('facebook_token', XenForo_Input::STRING);
        $facebookUser = XenForo_Helper_Facebook::getUserInfo($facebookToken);
        $userData = array();
        if (empty($facebookUser['id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_invalid_facebook_token'));
        }

        // create a provider key tied between current API client and Facebook ID
        // this needs to be done because Facebook uses app-scoped user IDs and they are
        // different from app to app (even with the same user)
        $providerKey = sprintf('%s_%s', $client['client_id'], $facebookUser['id']);

        // attempt #1: try to find the association using our provider key
        $facebookAssoc = $userExternalModel->getExternalAuthAssociation('facebook', $providerKey);
        if (!empty($facebookAssoc)) {
            return $this->_actionPostTokenNonStandard($client, $facebookAssoc['user_id']);
        }

        if (!empty($facebookUser['email'])) {
            // attempt #2: try to find user using email
            // this is a security risk but in most case it's acceptable
            // user who is knowledgeable can avoid this by do not associate a Facebook account
            $user = $userModel->getUserByEmail($facebookUser['email']);
            if ($user['user_state'] == 'valid') {
                $facebookAssoc = $userExternalModel->getExternalAuthAssociationForUser('facebook', $user['user_id']);
                if (!empty($facebookAssoc)) {
                    return $this->_actionPostTokenNonStandard($client, $facebookAssoc['user_id']);
                }
            }

            $userData['user_email'] = $facebookUser['email'];
        }

        if (!empty($facebookUser['name'])) {
            $testDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
            $testDw->set('username', $facebookUser['name']);
            if (!$testDw->hasErrors()) {
                // good username
                $userData['username'] = $facebookUser['name'];
            }
        }

        $extraData = array(
            'external_provider' => 'facebook',
            'external_provider_key' => $providerKey,
        );
        if (!empty($userData['user_email'])) {
            $extraData['user_email'] = $userData['user_email'];
        }
        $extraData = serialize($extraData);
        $extraTimestamp = time() + bdApi_Option::get('refreshTokenTTLDays') * 86400;
        $userData += array(
            'extra_data' => bdApi_Crypt::encryptTypeOne($extraData, $extraTimestamp),
            'extra_timestamp' => $extraTimestamp,
        );

        $data = array(
            'status' => 'ok',
            'message' => new XenForo_Phrase('bdapi_no_facebook_association_found'),
            'user_data' => $userData,
        );

        return $this->responseData('bdApi_ViewApi_OAuth_TokenFacebook_NoAssoc', $data);
    }

    public function actionPostTokenTwitter()
    {
        $client = $this->_getClientOrError();

        /* @var $userExternalModel XenForo_Model_UserExternal */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

        $twitterUri = $this->_input->filterSingle('twitter_uri', XenForo_Input::STRING);
        $twitterAuth = $this->_input->filterSingle('twitter_auth', XenForo_Input::STRING);
        $twitterClient = XenForo_Helper_Http::getClient($twitterUri);
        $twitterClient->setHeaders('Authorization', $twitterAuth);
        $twitterResponse = $twitterClient->request('GET');
        $twitterUser = @json_decode($twitterResponse->getBody(), true);
        if (empty($twitterUser['id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_invalid_twitter_token'));
        }

        $twitterAssoc = $userExternalModel->getExternalAuthAssociation('twitter', $twitterUser['id']);
        if (empty($twitterAssoc)) {
            $userData = array();

            if (!empty($twitterUser['screen_name'])) {
                $testDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
                $testDw->set('username', $twitterUser['screen_name']);
                if (!$testDw->hasErrors()) {
                    // good username
                    $userData['username'] = $twitterUser['screen_name'];
                }
            }

            $extraData = array(
                'external_provider' => 'twitter',
                'external_provider_key' => $twitterUser['id'],
            );
            $extraData = serialize($extraData);
            $extraTimestamp = time() + bdApi_Option::get('refreshTokenTTLDays') * 86400;
            $userData += array(
                'extra_data' => bdApi_Crypt::encryptTypeOne($extraData, $extraTimestamp),
                'extra_timestamp' => $extraTimestamp,
            );

            $data = array(
                'status' => 'ok',
                'message' => new XenForo_Phrase('bdapi_no_twitter_association_found'),
                'user_data' => $userData,
            );

            return $this->responseData('bdApi_ViewApi_OAuth_TokenTwitter_NoAssoc', $data);
        }

        return $this->_actionPostTokenNonStandard($client, $twitterAssoc['user_id']);
    }

    public function actionPostTokenGoogle()
    {
        $client = $this->_getClientOrError();

        /* @var $userExternalModel XenForo_Model_UserExternal */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

        $googleToken = $this->_input->filterSingle('google_token', XenForo_Input::STRING);
        $httpClient = XenForo_Helper_Http::getClient('https://www.googleapis.com/plus/v1/people/me');
        $httpClient->setParameterGet('access_token', $googleToken);
        $response = $httpClient->request('GET');
        $googleUser = json_decode($response->getBody(), true);
        if (empty($googleUser['id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_invalid_google_token'));
        }

        $googleAssoc = $userExternalModel->getExternalAuthAssociation('google', $googleUser['id']);
        if (empty($googleAssoc)) {
            $userData = array();

            if (!empty($googleUser['displayName'])) {
                $testDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
                $testDw->set('username', $googleUser['displayName']);
                if (!$testDw->hasErrors()) {
                    // good username
                    $userData['username'] = $googleUser['displayName'];
                }
            }

            if (!empty($googleUser['emails'])) {
                foreach ($googleUser['emails'] as $googleEmail) {
                    $userData['user_email'] = $googleEmail['value'];
                    break;
                }
            }

            if (!empty($googleUser['birthday'])) {
                if (preg_match('#^(?<year>\d+)-(?<month>\d+)-(?<day>\d+)$#', $googleUser['birthday'], $birthdayMatches)) {
                    $userData['user_dob_year'] = $birthdayMatches['year'];
                    $userData['user_dob_month'] = $birthdayMatches['month'];
                    $userData['user_dob_day'] = $birthdayMatches['day'];
                }
            }

            $extraData = array(
                'external_provider' => 'google',
                'external_provider_key' => $googleUser['id'],
            );
            if (!empty($userData['user_email'])) {
                $extraData['user_email'] = $userData['user_email'];
            }
            $extraData = serialize($extraData);
            $extraTimestamp = time() + bdApi_Option::get('refreshTokenTTLDays') * 86400;
            $userData += array(
                'extra_data' => bdApi_Crypt::encryptTypeOne($extraData, $extraTimestamp),
                'extra_timestamp' => $extraTimestamp,
            );

            $data = array(
                'status' => 'ok',
                'message' => new XenForo_Phrase('bdapi_no_google_association_found'),
                'user_data' => $userData,
            );

            return $this->responseData('bdApi_ViewApi_OAuth_TokenGoogle_NoAssoc', $data);
        }

        return $this->_actionPostTokenNonStandard($client, $googleAssoc['user_id']);
    }

    public function actionPostTokenAdmin()
    {
        $this->_assertRequiredScope('admincp');
        $this->_assertAdminPermission('user');

        $client = $this->_getClientOrError();

        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (empty($userId)) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_oauth_token_admin_requires_user_id'), 400);
        }

        return $this->_actionPostTokenNonStandard($client, $userId, false);
    }

    protected function _getClientOrError()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
        $session = bdApi_Data_Helper_Core::safeGetSession();

        $oauthClientId = $session->getOAuthClientId();
        $client = null;
        if (empty($oauthClientId)) {
            $inputClientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
            $inputClientSecret = $this->_input->filterSingle('client_secret', XenForo_Input::STRING);

            if (!empty($inputClientId) AND !empty($inputClientSecret)) {
                $client = $oauth2Model->getClientModel()->getClientById($inputClientId);
                if (!empty($client) AND !$oauth2Model->getClientModel()->verifySecret($client, $inputClientSecret)) {
                    throw $this->getNoPermissionResponseException();
                }
            }
        } else {
            $client = $oauth2Model->getClientModel()->getClientById($oauthClientId);
        }

        if (empty($client)) {
            throw $this->getNoPermissionResponseException();
        }

        return $client;
    }

    protected function _actionPostTokenNonStandard(array $client, $userId, $includeRefreshToken = true)
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
        $scopes = $oauth2Model->getAutoAndUserScopes($client['client_id'], $userId);

        $token = $oauth2Model->getServer()->createAccessToken($client['client_id'], $userId, $scopes, null, $includeRefreshToken);

        return $this->responseData('bdApi_ViewApi_OAuth_TokenNonStandard', $token);
    }

    protected function _getScopeForAction($action)
    {
        // no scope checking for this controller
        return false;
    }

}
