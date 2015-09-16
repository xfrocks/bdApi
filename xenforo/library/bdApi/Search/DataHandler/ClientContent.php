<?php

class bdApi_Search_DataHandler_ClientContent extends XenForo_Search_DataHandler_Abstract
{
    const CONTENT_TYPE = 'api_client_content';

    /** @var bdApi_Model_ClientContent */
    protected $_clientContentModel = null;

    protected function _insertIntoIndex(XenForo_Search_Indexer $indexer, array $data, array $parentData = null)
    {
        $metadata = array();
        $metadata['client_id'] = $data['client_id'];

        $indexer->insertIntoIndex(
            self::CONTENT_TYPE, $data['client_content_id'],
            $data['title'], $data['body'],
            $data['date'], $data['user_id'],
            0, $metadata
        );
    }

    protected function _updateIndex(XenForo_Search_Indexer $indexer, array $data, array $fieldUpdates)
    {
        $indexer->updateIndex(self::CONTENT_TYPE, $data['client_content_id'], $fieldUpdates);
    }

    protected function _deleteFromIndex(XenForo_Search_Indexer $indexer, array $dataList)
    {
        $clientContentIds = array();
        foreach ($dataList AS $data) {
            $clientContentIds[] = is_array($data) ? $data['client_content_id'] : $data;
        }

        $indexer->deleteFromIndex(self::CONTENT_TYPE, $clientContentIds);
    }

    public function rebuildIndex(XenForo_Search_Indexer $indexer, $lastId, $batchSize)
    {
        $clientContentIds = $this->_getClientContentModel()->getClientContentIdsInRange($lastId, $batchSize);
        if (!$clientContentIds) {
            return false;
        }

        $this->quickIndex($indexer, $clientContentIds);

        return max($clientContentIds);
    }

    public function quickIndex(XenForo_Search_Indexer $indexer, array $contentIds)
    {
        $clientContents = $this->_getClientContentModel()->getClientContents(array(
            'client_content_id' => $contentIds
        ));

        foreach ($clientContents AS $clientContent) {
            $this->insertIntoIndex($indexer, $clientContent);
        }

        return true;
    }

    public function getDataForResults(array $ids, array $viewingUser, array $resultsGrouped)
    {
        return $this->_getClientContentModel()->getClientContents(array(
            'client_content_id' => $ids
        ), array(
            'join' => bdApi_Model_ClientContent::FETCH_CLIENT
                | bdApi_Model_ClientContent::FETCH_USER,
        ));
    }

    public function canViewResult(array $result, array $viewingUser)
    {
        return true;
    }

    public function getResultDate(array $result)
    {
        return $result['date'];
    }

    public function renderResult(XenForo_View $view, array $result, array $search)
    {
        return $view->createTemplateObject('bdapi_search_result_client_content', array(
            'clientContent' => $result,
            'search' => $search,
        ));
    }

    public function getSearchContentTypes()
    {
        return array(self::CONTENT_TYPE);
    }

    /**
     * @return bdApi_Model_ClientContent
     */
    protected function _getClientContentModel()
    {
        if (!$this->_clientContentModel) {
            $this->_clientContentModel = XenForo_Model::create('bdApi_Model_ClientContent');
        }

        return $this->_clientContentModel;
    }
}