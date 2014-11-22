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
        return array('xf_bdapi_log' => array(
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
        ));
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

    /**
     * @return bdApi_Model_Log
     */
    protected function _getLogModel()
    {
        return $this->getModelFromCache('bdApi_Model_Log');
    }
}
