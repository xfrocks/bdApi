<?php

class bdApi_DataWriter_Log extends XenForo_DataWriter
{
    protected function _beginDbTransaction()
    {
        return false;
    }

    protected function _commitDbTransaction()
    {
        return false;
    }

    protected function _rollbackDbTransaction()
    {
        return false;
    }

    protected function _getFields()
    {
        return array(
            'xf_bdapi_log' => array(
                'log_id' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'autoIncrement' => true
                ),
                'client_id' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'maxLength' => 255,
                    'default' => ''
                ),
                'user_id' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'required' => true
                ),
                'ip_address' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'maxLength' => 50,
                    'default' => ''
                ),
                'request_date' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'required' => true
                ),
                'request_method' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'maxLength' => 10,
                    'default' => 'get'
                ),
                'request_uri' => array('type' => XenForo_DataWriter::TYPE_STRING),
                'request_data' => array('type' => XenForo_DataWriter::TYPE_SERIALIZED),
                'response_code' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'required' => true
                ),
                'response_output' => array('type' => XenForo_DataWriter::TYPE_SERIALIZED)
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'log_id')) {
            return false;
        }

        return array('xf_bdapi_log' => $this->_getLogModel()->getLogById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('log_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _preSave()
    {
        parent::_preSave();

        $syslogHost = bdApi_Option::getConfig('syslogHost');
        if ($syslogHost !== '') {
            $syslogPort = bdApi_Option::getConfig('syslogPort');
            $syslogPri = bdApi_Option::getConfig('syslogPri');
            $date = gmdate('M d H:i:s', $this->get('request_date'));
            $hostname = gethostname();

            $requestData = XenForo_Helper_Php::safeUnserialize($this->get('request_data'));

            $syslogLine = json_encode(array(
                'client_id' => $this->get('client_id'),
                'ip_address' => $this->get('ip_address'),
                'is_job' => !empty($requestData['_isApiJob']),
                'response_code' => $this->get('response_code'),
                'request_method' => $this->get('request_method'),
                'request_uri' => $this->get('request_uri'),
                'user_id' => $this->get('user_id'),
            ));

            // https://gist.github.com/troy/2220679
            $microtime = microtime(true);
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            $syslogMessage = "<{$syslogPri}> {$date} {$hostname} api: {$syslogLine}";
            $sent = socket_sendto($socket, $syslogMessage, strlen($syslogMessage), 0, $syslogHost, $syslogPort);
            socket_close($socket);
            $elapsed = microtime(true) - $microtime;

            if (XenForo_Application::debugMode()) {
                $requestData['_syslog'] = array(
                    'sent' => $sent,
                    'elapsed' => $elapsed,
                );
                $this->set('request_data', $requestData);
            }
        }
    }

    /**
     * @return bdApi_Model_Log
     */
    protected function _getLogModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdApi_Model_Log');
    }
}
