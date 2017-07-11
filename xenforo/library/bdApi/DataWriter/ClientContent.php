<?php

class bdApi_DataWriter_ClientContent extends XenForo_DataWriter
{
    protected function _getFields()
    {
        return array(
            'xf_bdapi_client_content' => array(
                'client_content_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
                'client_id' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
                'content_type' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'required' => true,
                    'maxLength' => 25
                ),
                'content_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
                'title' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
                'body' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),
                'date' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
                'link' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),
                'user_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
                'extra_data' => array('type' => XenForo_DataWriter::TYPE_SERIALIZED),
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'client_content_id')) {
            return false;
        }

        return array('xf_bdapi_client_content' => $this->_getClientContentModel()->getClientContentById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('client_content_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _postSave()
    {
        parent::_postSave();

        $this->_insertIntoSearchIndex();
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->_deleteFromSearchIndex();
    }

    protected function _insertIntoSearchIndex()
    {
        $indexer = new XenForo_Search_Indexer();
        $this->_getSearchDataHandler()->insertIntoIndex($indexer, $this->getMergedData());
    }

    protected function _deleteFromSearchIndex()
    {
        $indexer = new XenForo_Search_Indexer();
        $this->_getSearchDataHandler()->deleteFromIndex($indexer, $this->getMergedData());
    }

    /**
     * @return bdApi_Search_DataHandler_ClientContent
     */
    protected function _getSearchDataHandler()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return XenForo_Search_DataHandler_Abstract::create('bdApi_Search_DataHandler_ClientContent');
    }

    /**
     * @return bdApi_Model_ClientContent
     */
    protected function _getClientContentModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdApi_Model_ClientContent');
    }
}
