<?php

class bdApi_CacheRebuilder_ClientContentDeleteAll extends XenForo_CacheRebuilder_Abstract
{
    public function getRebuildMessage()
    {
        return new XenForo_Phrase('bdapi_client_contents');
    }

    public function rebuild($position = 0, array &$options = array(), &$detailedMessage = '')
    {
        if (empty($options['client_id'])) {
            return true;
        }

        $options['batch'] = isset($options['batch']) ? $options['batch'] : 50;
        $options['batch'] = max(1, $options['batch']);

        /** @var bdApi_Model_ClientContent $clientContentModel */
        $clientContentModel = XenForo_Model::create('bdApi_Model_ClientContent');
        $clientContents = $clientContentModel->getClientContents(array(
            'client_id' => $options['client_id'],
        ), array(
            'limit' => $options['batch'],
            'order' => 'client_content_id',
            'direction' => 'asc',
        ));
        if (empty($clientContents)) {
            return true;
        }

        foreach ($clientContents as $clientContentId => $clientContent) {
            $position = max($position, $clientContentId);

            /** @var bdApi_DataWriter_ClientContent $dw */
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_ClientContent');
            $dw->setExistingData($clientContent, true);
            $dw->delete();
        }

        $detailedMessage = XenForo_Locale::numberFormat($position);

        return $position;
    }
}
