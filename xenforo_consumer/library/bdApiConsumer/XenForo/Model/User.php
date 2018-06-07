<?php

class bdApiConsumer_XenForo_Model_User extends XFCP_bdApiConsumer_XenForo_Model_User
{
    public function validateAuthentication($nameOrEmail, $password, &$error = '')
    {
        $userId = parent::validateAuthentication($nameOrEmail, $password, $error);

        if (empty($userId) AND strpos($nameOrEmail, '@') === false AND bdApiConsumer_Option::get('takeOver', 'login')) {
            // try to login with external providers
            $providers = bdApiConsumer_Option::getProviders();

            foreach ($providers as $provider) {
                $externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromUsernamePassword($provider, $nameOrEmail, $password);
                if (empty($externalToken)) {
                    continue;
                }

                $externalVisitor = bdApiConsumer_Helper_Api::getVisitor($provider, $externalToken['access_token']);
                if (empty($externalVisitor)) {
                    continue;
                }

                /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
                $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
                $existingAssoc = $userExternalModel->getExternalAuthAssociation(
                    $userExternalModel->bdApiConsumer_getProviderCode($provider),
                    $externalVisitor['user_id']
                );
                if (!empty($existingAssoc)) {
                    // yay, found an associated user!
                    $error = '';
                    $userExternalModel->bdApiConsumer_updateExternalAuthAssociation(
                        $provider,
                        $externalVisitor['user_id'],
                        $existingAssoc['user_id'],
                        $externalVisitor + array('token' => $externalToken)
                    );
                    return $existingAssoc['user_id'];
                }

                $existingUser = $this->getUserByEmail($externalVisitor['user_email']);
                if (!empty($existingUser)) {
                    // this is not good, an user with matched email
                    // this user will have to associate manually
                    continue;
                }

                $sameName = $this->getUserByName($externalVisitor['username']);
                if (!empty($sameName)) {
                    // not good
                    continue;
                }
                $data = array('username' => $externalVisitor['username']);

                if (bdApiConsumer_Option::get('autoRegister') === 'id_sync') {
                    // additionally look for user with same ID
                    $sameId = $this->getUserById($externalVisitor['user_id']);
                    if (!empty($sameId)) {
                        // not good
                        continue;
                    }

                    $data['user_id'] = $externalVisitor['user_id'];
                }

                $user = bdApiConsumer_Helper_AutoRegister::createUser(
                    $data,
                    $provider,
                    $externalToken,
                    $externalVisitor,
                    $userExternalModel
                );

                if (!empty($user)) {
                    $error = '';
                    return $user['user_id'];
                }
            }
        }

        return $userId;
    }
}
