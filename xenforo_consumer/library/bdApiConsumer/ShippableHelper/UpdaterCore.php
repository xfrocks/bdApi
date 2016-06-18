<?php

// updated by DevHelper_Helper_ShippableHelper at 2016-06-18T11:13:03+00:00

/**
 * Class bdApiConsumer_ShippableHelper_UpdaterCore
 * @version 1
 * @see DevHelper_Helper_ShippableHelper_UpdaterCore
 */
class bdApiConsumer_ShippableHelper_UpdaterCore
{
    const PARAM_ENABLE = 'updater-enable';
    const PARAM_DISABLE = 'updater-disable';
    const PARAM_FORCE = 'updater-force';
    const PARAM_DOWNLOAD = 'updater-download';
    const PARAM_IMPORT_XML = 'updater-importXml';
    const PARAM_IMPORT_XML_PATH = 'updater-xmlPath';
    const PARAM_AUTHORIZE = 'updater-authorize';
    const PARAM_ACCESS_TOKEN = 'updater-accessToken';
    const PARAM_IGNORE = 'updater-ignore';

    protected static $_isBeta = true;

    /**
     * Bootstraps the Updater.
     * This routine cannot be executed at `init_dependencies` due to version resolving,
     * it will run at `front_controller_pre_route` instead. This method should only be called once
     * for each <code>$config.apiUrl</code.
     *
     * @param array $config
     *
     * @internal
     */
    public static function bootstrap(array $config)
    {
        if (isset($GLOBALS[bdApiConsumer_ShippableHelper_Updater::KEY]['setupMethod'][$config['apiUrl']])) {
            if (XenForo_Application::debugMode()) {
                die(sprintf('Updater for %s has been setup by %s already!', $config['apiUrl'],
                    $GLOBALS[bdApiConsumer_ShippableHelper_Updater::KEY]['setupMethod'][$config['apiUrl']]));
            }
            return;
        }
        $GLOBALS[bdApiConsumer_ShippableHelper_Updater::KEY]['setupMethod'][$config['apiUrl']] = __METHOD__;

        if (self::$_config !== null) {
            if (XenForo_Application::debugMode()) {
                die(sprintf('%s has been setup to run with %s already!', __METHOD__, self::$_config['apiUrl']));
            }
            return;
        }
        self::_updateSelf($config);

        // from this point, we no longer need to care about other implementations of Updater
        // all checks have been passed and indicated that the current one is the latest version

        XenForo_CodeEvent::addListener('front_controller_pre_view', array(__CLASS__, 'front_controller_pre_view'));
    }

    /**
     * Handles pre_view code event and execute Updater routines appropriately.
     *
     * @param XenForo_FrontController $fc
     * @param XenForo_ControllerResponse_Abstract $controllerResponse
     * @param XenForo_ViewRenderer_Abstract $viewRenderer
     * @param array $containerParams
     *
     * @internal
     */
    public static function front_controller_pre_view(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_FrontController $fc,
        XenForo_ControllerResponse_Abstract &$controllerResponse,
        XenForo_ViewRenderer_Abstract &$viewRenderer,
        array &$containerParams
    ) {
        if (!$controllerResponse instanceof XenForo_ControllerResponse_View) {
            return;
        }

        switch ($controllerResponse->viewName) {
            case 'XenForo_ViewAdmin_AddOn_List':
                self::_onAddOnList($controllerResponse, $containerParams);
                break;
            case 'XenForo_ViewAdmin_AddOn_Upgrade':
                self::_onAddOnUpgrade($controllerResponse);
                break;
        }
    }

    private static function _onAddOnList(XenForo_ControllerResponse_View &$controllerResponse, array &$containerParams)
    {
        $message = '';

        if (!empty($_GET[self::PARAM_ENABLE])
            && $_GET[self::PARAM_ENABLE] === self::$_config['apiUrl']
        ) {
            self::$_config['configured'] = true;
            self::$_config['enabled'] = true;
            self::_saveConfig(self::$_config);
            $controllerResponse = new XenForo_ControllerResponse_Redirect();
            $controllerResponse->redirectType = XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED;
            $controllerResponse->redirectTarget = XenForo_Link::buildAdminLink('add-ons');
            return;
        }

        if (!empty($_GET[self::PARAM_DISABLE])
            && $_GET[self::PARAM_DISABLE] === self::$_config['apiUrl']
        ) {
            self::$_config['configured'] = true;
            self::$_config['enabled'] = false;
            self::_saveConfig(self::$_config);
            $controllerResponse = new XenForo_ControllerResponse_Redirect();
            $controllerResponse->redirectType = XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED;
            $controllerResponse->redirectTarget = XenForo_Link::buildAdminLink('add-ons');
            return;
        }

        if (empty(self::$_config['configured'])) {
            if (!self::$_isBeta
                || XenForo_Application::debugMode()
            ) {
                $enabledAddOnIds = self::_getEnabledAddOnIds(self::$_config['addOnIds']);

                if (!empty($enabledAddOnIds)) {
                    /** @noinspection HtmlUnknownTarget */
                    $message .= sprintf('Updater for %s: <a href="%s">enable</a> or <a href="%s">disable</a>?',
                        implode(', ', $enabledAddOnIds),
                        XenForo_Link::buildAdminLink('add-ons', null, array(
                            self::PARAM_ENABLE => self::$_config['apiUrl'],
                        )),
                        XenForo_Link::buildAdminLink('add-ons', null, array(
                            self::PARAM_DISABLE => self::$_config['apiUrl'],
                        ))
                    );

                    if (self::$_isBeta) {
                        $message .= ' Warning: The Updater is currently in beta.';
                    }
                }
            }
        } elseif (self::$_config['configured']) {
            $forceRefresh = (!empty($_GET[self::PARAM_FORCE]) && $_GET[self::PARAM_FORCE] === self::$_config['apiUrl']);
            $data = self::_refreshData(self::$_config['apiUrl'], self::$_config['addOnIds'], $forceRefresh);

            if (!empty($data)) {
                $message .= self::_onAddOnShowStatuses($data, $forceRefresh);
            }

        }

        $paramKey = '_' . md5(self::$_config['apiUrl']);
        $containerParams[$paramKey] = array('message' => $message);
    }

    private static function _onAddOnShowStatuses(array $data, $forceRefresh)
    {
        $statuses = '';
        $xenAddOns = XenForo_Application::get('addOns');

        foreach ($xenAddOns as $addOnId => $addOnVersionId) {
            if (!in_array($addOnId, self::$_config['addOnIds'], true)) {
                continue;
            }

            if (!isset($data[$addOnId])) {
                if ($forceRefresh) {
                    $statuses .= sprintf('%1$s cannot be verified with the Updater Server.<br />', $addOnId);
                }
                continue;
            }

            if ($addOnVersionId < $data[$addOnId]['version_id']) {
                $ignoredVersionId = 0;
                if (!empty(self::$_config['ignored'][$addOnId])) {
                    $ignoredVersionId = self::$_config['ignored'][$addOnId];
                }
                $isIgnored = $data[$addOnId]['version_id'] <= $ignoredVersionId;

                $actions = array();
                if ($forceRefresh
                    || !$isIgnored
                ) {
                    if (!empty($data[$addOnId]['links']['permalink'])) {
                        /** @noinspection HtmlUnknownTarget */
                        $actions[] = sprintf('<a href="%1$s">read more</a>', $data[$addOnId]['links']['permalink']);
                    }

                    if (!empty($data[$addOnId]['links']['content'])) {
                        /** @noinspection HtmlUnknownTarget */
                        $actions[] = sprintf('<a href="%1$s">update</a>',
                            XenForo_Link::buildAdminLink('full:add-ons/upgrade',
                                array('addon_id' => $addOnId),
                                array(self::PARAM_DOWNLOAD => self::$_config['apiUrl'])));
                    } elseif (!empty($data[$addOnId]['links']['authorize'])) {
                        $authorizeUrl = sprintf('%s&redirect_uri=%s',
                            $data[$addOnId]['links']['authorize'],
                            rawurlencode(XenForo_Link::buildAdminLink('full:add-ons/upgrade',
                                array('addon_id' => $addOnId),
                                array(self::PARAM_AUTHORIZE => self::$_config['apiUrl'])))
                        );

                        /** @noinspection HtmlUnknownTarget */
                        $actions[] = sprintf('<a href="%1$s">authorize an update</a>', $authorizeUrl);
                    }
                }

                if (!empty($actions)) {
                    $outOfDateMessage = sprintf('%1$s is out of date, latest version is v%3$s (#%4$d, yours is #%2$d).',
                        $addOnId,
                        $addOnVersionId,
                        $data[$addOnId]['version_string'],
                        $data[$addOnId]['version_id']);

                    $ignoreMessage = '';
                    if (!$isIgnored) {
                        /** @noinspection HtmlUnknownTarget */
                        $ignoreMessage = sprintf(' or <a href="%1$s">ignore this version</a>',
                            XenForo_Link::buildAdminLink('full:add-ons/upgrade',
                                array('addon_id' => $addOnId),
                                array(self::PARAM_IGNORE => self::$_config['apiUrl'])));
                    }

                    $statuses .= sprintf('%1$s You may: %2$s%3$s.<br />',
                        $outOfDateMessage,
                        implode(', ', $actions),
                        $ignoreMessage);
                }
            } else {
                if ($forceRefresh) {
                    if ($addOnVersionId > $data[$addOnId]['version_id']) {
                        $statuses .= sprintf('%1$s appears to be even newer than'
                            . ' reported from the Updater Server: their v%3$s (#%4$d) vs. your #%2$d.'
                            . ' Awesome!<br />',
                            $addOnId,
                            $addOnVersionId,
                            $data[$addOnId]['version_string'],
                            $data[$addOnId]['version_id']);
                    } else {
                        $statuses .= sprintf('%1$s is up to date.<br />', $addOnId);
                    }
                }
            }
        }

        return $statuses;
    }

    private static function _onAddOnUpgrade(XenForo_ControllerResponse_View &$controllerResponse)
    {
        if (!empty($_GET[self::PARAM_AUTHORIZE])
            && $_GET[self::PARAM_AUTHORIZE] === self::$_config['apiUrl']
        ) {
            $downloadLink = XenForo_Link::buildAdminLink(
                'full:add-ons/upgrade',
                $controllerResponse->params['addOn'],
                array(
                    self::PARAM_DOWNLOAD => self::$_config['apiUrl']
                )
            );
            $downloadLinkJson = json_encode($downloadLink);
            $accessTokenParamJson = json_encode(self::PARAM_ACCESS_TOKEN);

            $js = <<<EOF
<script>
var hash = window.location.hash.substr(1);
var regex = /access_token=(.+?)(&|$)/;
var match = hash.match(regex);
if (match) {
    var accessToken = match[1];
    var downloadLink = $downloadLinkJson;
    var redirect = downloadLink + '&' + $accessTokenParamJson + '=' + encodeURIComponent(accessToken);

    window.location = redirect;
}
</script>
EOF;
            die($js);
        }

        if (!empty($_GET[self::PARAM_DOWNLOAD])
            && $_GET[self::PARAM_DOWNLOAD] === self::$_config['apiUrl']
        ) {
            $accessToken = '';
            if (!empty($_GET[self::PARAM_ACCESS_TOKEN])) {
                $accessToken = $_GET[self::PARAM_ACCESS_TOKEN];
            }

            $addOnId = $controllerResponse->params['addOn']['addon_id'];
            $data = self::_fetchData(self::$_config['apiUrl'], array($addOnId), $accessToken);

            if (!empty($data[$addOnId]['links']['content'])) {
                $tempFile = bdApiConsumer_ShippableHelper_TempFile::download($data[$addOnId]['links']['content']);
                $xmlPath = self::_updateAddOnFiles($addOnId, $tempFile);

                /** @noinspection BadExpressionStatementJS */
                die(sprintf('<script>window.location = %s;</script>',
                    json_encode(XenForo_Link::buildAdminLink('add-ons/upgrade', array('addon_id' => $addOnId), array(
                        self::PARAM_IMPORT_XML => self::$_config['apiUrl'],
                        self::PARAM_IMPORT_XML_PATH => $xmlPath
                    ))))
                );
            }
        }

        if (!empty($_GET[self::PARAM_IMPORT_XML])
            && $_GET[self::PARAM_IMPORT_XML] === self::$_config['apiUrl']
            && !empty($_GET[self::PARAM_IMPORT_XML_PATH])
        ) {
            $addOnId = $controllerResponse->params['addOn']['addon_id'];
            $xmlPath = $_GET[self::PARAM_IMPORT_XML_PATH];

            /** @var XenForo_Model_AddOn $addOnModel */
            $addOnModel = XenForo_Model::create('XenForo_Model_AddOn');
            $addOnModel->installAddOnXmlFromFile($xmlPath, $addOnId);

            $addOn = $addOnModel->getAddOnById($addOnId);
            echo(sprintf('Updated add-on %1$s to v%2$s (#%3$d)',
                $addOn['title'], $addOn['version_string'], $addOn['version_id']));

            /** @noinspection BadExpressionStatementJS */
            die(sprintf('<script>window.f=function(){window.location=%s;};'
                . 'window.setTimeout("f()",5000);</script>',
                json_encode(XenForo_Link::buildAdminLink('full:tools/run-deferred', null, array(
                    'redirect' => XenForo_Link::buildAdminLink('full:add-ons')
                )))));
        }

        if (!empty($_GET[self::PARAM_IGNORE])
            && $_GET[self::PARAM_IGNORE] === self::$_config['apiUrl']
        ) {
            $addOnId = $controllerResponse->params['addOn']['addon_id'];
            $data = self::_refreshData(self::$_config['apiUrl'], self::$_config['addOnIds'], false);
            if (!empty($data[$addOnId]['version_id'])) {
                self::$_config['ignored'][$addOnId] = $data[$addOnId]['version_id'];
                self::_saveConfig(self::$_config);
            }

            $controllerResponse = new XenForo_ControllerResponse_Redirect();
            $controllerResponse->redirectType = XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED;
            $controllerResponse->redirectTarget = XenForo_Link::buildAdminLink('add-ons');
        }
    }

    private static function _updateSelf(array $config)
    {
        $options = XenForo_Application::getOptions();

        $configOptionId = bdApiConsumer_ShippableHelper_Updater::getConfigOptionId($config['apiUrl']);
        $templateTitle = $configOptionId;
        $templateModKey = $configOptionId;

        $configVersion = $config['version'];
        $configUpdated = array();
        if ($configVersion === 0) {
            $configUpdated[] = '$config';
        }

        if ($configVersion < bdApiConsumer_ShippableHelper_Updater::$_version) {
            if ($configVersion < 2015100121) {
                /** @var XenForo_DataWriter_AdminTemplate $templateDw */
                $templateDw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');

                /** @var XenForo_Model_AdminTemplate $templateModel */
                $templateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');
                $template = $templateModel->getAdminTemplateByTitle($templateTitle);
                if (!empty($template)) {
                    $templateDw->setExistingData($template, true);
                }

                $templateDw->bulkSet(array(
                    'title' => $templateTitle,
                    'template' => self::$_template,
                ));
                $templateDw->save();

                $configUpdated[] = '$templateDw';
            }

            if ($configVersion < 2015100107) {
                /** @var XenForo_DataWriter_AdminTemplateModification $templateModDw */
                $templateModDw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplateModification');

                /** @var XenForo_Model_AdminTemplateModification $templateModModel */
                $templateModModel = XenForo_Model::create('XenForo_Model_AdminTemplateModification');
                $templateMod = $templateModModel->getModificationByKey($templateModKey);
                if (!empty($templateMod)) {
                    $templateModDw->setExistingData($templateMod, true);
                }

                $templateModDw->bulkSet(array(
                    'template' => 'content_header',
                    'modification_key' => $templateModKey,
                    'description' => $config['apiUrl'],
                    'action' => 'preg_replace',
                    'find' => '#^.+$#s',
                    'replace' => '$0' . str_replace('md5', md5($config['apiUrl']), self::$_templateMod),
                ));
                $templateModDw->save();

                $configUpdated[] = '$templateModDw';
            }

            $config['version'] = bdApiConsumer_ShippableHelper_Updater::$_version;
            $configUpdated[] = '$config["version"]';
        }

        $addOnIdsUpdated = false;
        foreach ($GLOBALS[bdApiConsumer_ShippableHelper_Updater::KEY][$config['apiUrl']]['addOns'] as $addOnId => $enabled) {
            if (!in_array($addOnId, $config['addOnIds'], true)) {
                $config['addOnIds'][] = $addOnId;
                $addOnIdsUpdated = true;
            }
        }
        if ($addOnIdsUpdated) {
            sort($config['addOnIds']);
            $configUpdated[] = '$config["addOnIds"]';
        }

        if (count($configUpdated) > 0) {
            /** @var XenForo_DataWriter_Option $optionDw */
            $optionDw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
            if ($configVersion > 0) {
                $optionDw->setExistingData($configOptionId);
            }
            $optionDw->bulkSet(array(
                'option_id' => $configOptionId,
                'option_value' => $config,
                'default_value' => 'a:0:{}',
                'edit_format' => 'template',
                'edit_format_params' => $templateTitle,
                'data_type' => 'array',
                'sub_options' => '*',
            ));
            $optionDw->setRelations(array(
                'debug' => 9999,
            ));
            $optionDw->setExtraData(XenForo_DataWriter_Option::DATA_TITLE, 'Add-on Updates'
                . (self::$_isBeta ? ' (BETA)' : ''));
            $optionDw->setExtraData(XenForo_DataWriter_Option::DATA_EXPLAIN, 'Turn on this option if you want to'
                . ' check for add-on updates from <a href="' . $config['apiUrl'] . '" target="_blank">'
                . $config['apiUrl'] . '</a> regularly. If you want to perform update check immediately, <a href="'
                . XenForo_Link::buildAdminLink('add-ons', null, array(self::PARAM_FORCE => $config['apiUrl']))
                . '" target="_blank">click here</a>.');
            $optionDw->save();
            $options->set($configOptionId, $config);

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('%s($apiUrl=%s): $configVersion=%d,'
                    . ' self::$_version=%d, $configUpdated=%s',
                    __METHOD__, $config['apiUrl'], var_export($configVersion, true),
                    var_export(bdApiConsumer_ShippableHelper_Updater::$_version, true),
                    var_export($configUpdated, true)));
            }
        }

        self::$_config = $config;
    }

    private static function _updateAddOnFiles($addOnId, $tempFile)
    {
        $tempDir = $tempFile . '_extracted';

        XenForo_Helper_File::createDirectory($tempDir, false);
        XenForo_Helper_File::makeWritableByFtpUser($tempDir);

        $decompress = new Zend_Filter_Decompress(array(
            'adapter' => 'Zip',
            'options' => array('target' => $tempDir)
        ));

        if (!$decompress->filter($tempFile)) {
            throw new XenForo_Exception('Unable to extract add-on package.', true);
        }

        $uploadDir = sprintf('%s/upload', $tempDir);
        if (!is_dir($uploadDir)) {
            throw new XenForo_Exception('Unsupported add-on package (no "upload" directory found).', true);
        }

        $xfDir = dirname(XenForo_Autoloader::getInstance()->getRootDir());
        $files = self::_verifyAddOnFiles($uploadDir, $xfDir);

        $xmlPath = self::_findXmlPath($addOnId, $tempDir, $xfDir, $files);

        self::_moveAddOnFiles($uploadDir, $xfDir, $files);

        return $xmlPath;
    }

    private static function _verifyAddOnFiles($packageDir, $xfDir, $relativePath = null)
    {
        $files = array();

        $dir = sprintf('%s/%s', $packageDir, $relativePath);
        $entries = scandir($dir);
        $subDirs = array();

        foreach ($entries as $entry) {
            if (substr($entry, 0, 1) === '.') {
                // ignore hidden files
                continue;
            }

            if ($relativePath !== null) {
                $fileRelativePath = sprintf('%s/%s', $relativePath, $entry);
            } else {
                $fileRelativePath = $entry;
            }

            if (is_file(sprintf('%s/%s', $packageDir, $fileRelativePath))) {
                $files[] = $fileRelativePath;

                $fileSystemPath = sprintf('%s/%s', $xfDir, $fileRelativePath);
                if (is_file($fileSystemPath)) {
                    if (!is_writable($fileSystemPath)) {
                        throw new XenForo_Exception('File is not writable: ' . $fileSystemPath, true);
                    }
                } elseif (is_dir($fileSystemPath)) {
                    throw new XenForo_Exception('File/directory conflict: ' . $fileSystemPath, true);
                } else {
                    $parentOfFileSystemPath = dirname($fileSystemPath);
                    if (is_dir($parentOfFileSystemPath)) {
                        if (!is_writable($parentOfFileSystemPath)) {
                            throw new XenForo_Exception('Directory is not writable: '
                                . $parentOfFileSystemPath, true);
                        }
                    } else {
                        if (!XenForo_Helper_File::createDirectory($parentOfFileSystemPath)) {
                            throw new XenForo_Exception('Directory cannot be created: '
                                . $parentOfFileSystemPath, true);
                        }
                    }
                }
            } else {
                $subDirs[] = $fileRelativePath;
            }
        }

        foreach ($subDirs as $subDir) {
            $files = array_merge($files, self::_verifyAddOnFiles($packageDir, $xfDir, $subDir));
        }

        return $files;
    }

    private static function _moveAddOnFiles($packageDir, $xfDir, array $files)
    {
        foreach ($files as $file) {
            $packagePath = sprintf('%s/%s', $packageDir, $file);
            $systemPath = sprintf('%s/%s', $xfDir, $file);

            if (!XenForo_Helper_File::safeRename($packagePath, $systemPath)) {
                throw new XenForo_Exception('File cannot be updated: ' . $file);
            }
        }
    }

    private static function _findXmlPath($addOnId, $tempDir, $xfDir, array $files)
    {
        foreach ($files as $file) {
            $fileName = basename($file);
            if (preg_match('#^addon-(?<id>.+)\.xml$#', $fileName, $matches)) {
                if ($addOnId === $matches['id']) {
                    return sprintf('%s/%s', $xfDir, $file);
                }
            }
        }

        $entries = scandir($tempDir);
        foreach ($entries as $entry) {
            if (preg_match('#^addon-(?<id>.+)\.xml$#', $entry, $matches)) {
                if ($addOnId === $matches['id']) {
                    return sprintf('%s/%s', $tempDir, $entry);
                }
            }
        }

        throw new XenForo_Exception('Unsupported add-on package (no xml found).', true);
    }

    public static function _uninstallSelf($apiUrl)
    {
        $configOptionId = bdApiConsumer_ShippableHelper_Updater::getConfigOptionId($apiUrl);
        $templateTitle = $configOptionId;
        $templateModKey = $configOptionId;

        try {
            XenForo_Db::beginTransaction();

            /** @var XenForo_Model_AdminTemplate $templateModel */
            $templateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');
            $template = $templateModel->getAdminTemplateByTitle($templateTitle);
            if (!empty($template)) {
                /** @var XenForo_DataWriter_AdminTemplate $templateDw */
                $templateDw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
                $templateDw->setExistingData($template, true);
                $templateDw->delete();
            }


            /** @var XenForo_Model_AdminTemplateModification $templateModModel */
            $templateModModel = XenForo_Model::create('XenForo_Model_AdminTemplateModification');
            $templateMod = $templateModModel->getModificationByKey($templateModKey);
            if (!empty($templateMod)) {
                /** @var XenForo_DataWriter_AdminTemplateModification $templateModDw */
                $templateModDw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplateModification');
                $templateModDw->setExistingData($templateMod, true);
                $templateModDw->delete();
            }

            /** @var XenForo_DataWriter_Option $optionDw */
            $optionDw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
            $optionDw->setExistingData($configOptionId);
            $optionDw->delete();

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('%s($apiUrl=%s): ok',
                    __METHOD__, $apiUrl));
            }

            XenForo_Db::commit();
        } catch (XenForo_Exception $e) {
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('%s($apiUrl=%s): %s',
                    __METHOD__, $apiUrl, $e->__toString()));
            }

            XenForo_Db::rollback();
        }
    }

    private static function _refreshData($apiUrl, array $addOnIds, $forceRefresh)
    {
        $data = ($forceRefresh ? null : self::_getCache($apiUrl));
        if (empty($data)
            || $data['version'] < bdApiConsumer_ShippableHelper_Updater::$_version
            || $data['timestamp'] < XenForo_Application::$time - 86400
        ) {
            $data = array(
                'version' => bdApiConsumer_ShippableHelper_Updater::$_version,
                'timestamp' => time(),
                'json' => self::_fetchData($apiUrl, $addOnIds),
            );

            self::_setCache($apiUrl, $data);
        }

        return $data['json'];
    }

    private static function _fetchData($url, array $addOnIds, $accessToken = '')
    {
        if (empty($addOnIds)) {
            return array();
        }

        $url .= sprintf('%s_version=%d', ((strpos($url, '?') === false) ? '?' : '&'),
            bdApiConsumer_ShippableHelper_Updater::$_version);
        if (!empty($accessToken)) {
            $url .= sprintf('&oauth_token=%s', rawurlencode($accessToken));
        }
        foreach ($addOnIds as $addOnId) {
            $url .= sprintf('&ids[]=%s', rawurlencode($addOnId));
        }

        $client = XenForo_Helper_Http::getClient($url);

        try {
            $response = $client->request('GET');

            $responseStatus = $response->getStatus();
            $responseBody = $response->getBody();
        } catch (Exception $e) {
            $responseStatus = 503;
            $responseBody = $e->getMessage();
        }

        $json = null;
        if ($responseStatus === 200) {
            $json = @json_decode($responseBody, true);

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('%s($url=%s): status=%d, json=%s',
                    __METHOD__, $url,
                    var_export($responseStatus, true), var_export($json, true)));
            }
        } else {
            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('%s($url=%s): status=%d, body=%s',
                    __METHOD__, $url,
                    var_export($responseStatus, true), var_export($responseBody, true)));
            }
        }

        return $json;
    }

    private static function _getCache($apiUrl)
    {
        /** @var XenForo_Model_DataRegistry $dataRegistryModel */
        $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
        $data = $dataRegistryModel->get(bdApiConsumer_ShippableHelper_Updater::KEY);

        if (!empty($data)
            && isset($data[$apiUrl])
        ) {
            return $data[$apiUrl];
        }

        return array();
    }

    private static function _setCache($apiUrl, array $thisData)
    {
        /** @var XenForo_Model_DataRegistry $dataRegistryModel */
        $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
        $data = $dataRegistryModel->get(bdApiConsumer_ShippableHelper_Updater::KEY);
        if (empty($data)) {
            $data = array();
        }
        $data[$apiUrl] = $thisData;
        $dataRegistryModel->set(bdApiConsumer_ShippableHelper_Updater::KEY, $data);
        return true;
    }

    private static function _saveConfig(array $config)
    {
        $configOptionId = bdApiConsumer_ShippableHelper_Updater::getConfigOptionId($config['apiUrl']);

        /** @var XenForo_DataWriter_Option $optionDw */
        $optionDw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
        $optionDw->setExistingData($configOptionId);
        $optionDw->set('option_value', $config);

        if ($optionDw->save()) {
            XenForo_Application::getOptions()->set($configOptionId, $config);

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, sprintf('%s($apiUrl=%s)',
                    __METHOD__, $config['apiUrl']));
            }

            return true;
        }

        return false;
    }

    private static function _getEnabledAddOnIds($addOnIds)
    {
        $xenAddOns = XenForo_Application::get('addOns');
        $enabledIds = array();
        foreach ($addOnIds as $configAddOnId) {
            if (isset($xenAddOns[$configAddOnId])) {
                $enabledIds[] = $configAddOnId;
            }
        }

        return $enabledIds;
    }

    private static $_template = '
<xen:set var="$addOnListStr">
    <xen:foreach loop="$preparedOption.option_value.addOnIds" value="$configAddOnId">
        <xen:foreach loop="$xenAddOns" key="$xenAddOnId" value="$xenAddOnVersionId">
            <xen:if is="{$configAddOnId} == {$xenAddOnId}">
               {$configAddOnId}
            </xen:if>
        </xen:foreach>
    </xen:foreach>
</xen:set>

<xen:checkboxunit label="{$preparedOption.title}">
	<xen:explain>
	    {xen:raw $preparedOption.explain}
	    Supported add-ons: {$addOnListStr}</xen:explain>
    <xen:option name="{$fieldPrefix}[{$preparedOption.option_id}][enabled]" value="1"
        label="{xen:phrase enabled}"
        selected="{$preparedOption.option_value.enabled}" />
	<xen:html>
		<input type="hidden" name="{$listedFieldName}" value="{$preparedOption.option_id}" />
		{xen:raw $editLink}

		<input type="hidden" name="{$fieldPrefix}[{$preparedOption.option_id}][configured]" value="1" />
		<input type="hidden" name="{$fieldPrefix}[{$preparedOption.option_id}][version]" value="{$preparedOption.option_value.version}" />
		<input type="hidden" name="{$fieldPrefix}[{$preparedOption.option_id}][apiUrl]" value="{$preparedOption.option_value.apiUrl}" />
		<xen:foreach loop="$preparedOption.option_value.addOnIds" value="$addOnId">
            <input type="hidden" name="{$fieldPrefix}[{$preparedOption.option_id}][addOnIds][]" value="{$addOnId}" />
        </xen:foreach>
	</xen:html>
</xen:checkboxunit>';

    private static $_templateMod = '
<xen:if is="{$_md5.message}">
    <p class="importantMessage" style="text-align: left">{xen:raw $_md5.message}</p>
</xen:if>';

    private static $_config = null;
}