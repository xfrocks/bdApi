<?php

class bdApiConsumer_Helper_AutoRegister
{
    public static function suggestUserName($username, XenForo_Model_User $userModel)
    {
        if (preg_match('#[^0-9]([0-9]+)$#', $username, $matches, PREG_OFFSET_CAPTURE)) {
            $i = $matches[1][0];
            $origName = trim(substr($username, 0, $matches[1][1]));
        } else {
            $i = 2;
            $origName = $username;
        }

        while ($userModel->getUserByName($username)) {
            $username = $origName . ' ' . $i++;
        }

        return $username;
    }

    public static function createUser(
        array $data,
        array $provider,
        array $externalToken,
        array $externalVisitor,
        XenForo_Model_UserExternal $userExternalModel)
    {
        $user = null;

        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $options = XenForo_Application::get('options');

        /** @var XenForo_DataWriter_User $writer */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        if ($options->registrationDefaults) {
            $writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
        }

        if (!isset($data['timezone']) AND isset($externalVisitor['user_timezone_offset'])) {
            $tzOffset = $externalVisitor['user_timezone_offset'];
            $tzName = timezone_name_from_abbr('', $tzOffset, 1);
            if ($tzName !== false) {
                $data['timezone'] = $tzName;
            }
        }

        if (!empty($data['user_id'])) {
            $writer->setImportMode(true);
        }
        $writer->bulkSet($data);
        if (!empty($data['user_id'])) {
            $writer->setImportMode(false);
        }

        $writer->set('email', $externalVisitor['user_email']);

        if (!empty($externalVisitor['user_gender'])) {
            $writer->set('gender', $externalVisitor['user_gender']);
        }

        if (!empty($externalVisitor['user_dob_day'])
            && !empty($externalVisitor['user_dob_month'])
            && !empty($externalVisitor['user_dob_year'])
        ) {
            $writer->set('dob_day', $externalVisitor['user_dob_day']);
            $writer->set('dob_month', $externalVisitor['user_dob_month']);
            $writer->set('dob_year', $externalVisitor['user_dob_year']);
        }

        if (!empty($externalVisitor['user_register_date'])) {
            $writer->set('register_date', $externalVisitor['user_register_date']);
        }

        $userExternalModel->bdApiConsumer_syncUpOnRegistration($writer, $externalToken, $externalVisitor);

        $auth = XenForo_Authentication_Abstract::create('XenForo_Authentication_NoPassword');
        $writer->set('scheme_class', $auth->getClassName());
        $writer->set('data', $auth->generate(''), 'xf_user_authenticate');

        $writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
        $writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));

        $writer->advanceRegistrationUserState(false);

        // TODO: option for extra user group

        $writer->preSave();
        if ($writer->hasErrors()) {
            return $user;
        }

        try {
            $writer->save();
            $user = $writer->getMergedData();

            $userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider, $externalVisitor['user_id'],
                $user['user_id'], array_merge($externalVisitor, array('token' => $externalToken)));

            XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register_api_consumer');
        } catch (XenForo_Exception $e) {
            if (XenForo_Application::debugMode()) {
                XenForo_Error::logException($e, false);
            }
        }

        return $user;
    }

}
