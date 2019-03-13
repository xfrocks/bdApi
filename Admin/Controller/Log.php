<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Mvc\ParameterBag;

class Log extends Entity
{
    protected function getShortName()
    {
        return 'Xfrocks\Api:Log';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_log';
    }

    protected function getRoutePrefix()
    {
        return 'api-logs';
    }

    protected function supportsAdding()
    {
        return false;
    }

    protected function supportsEditing()
    {
        return false;
    }

    protected function supportsViewing()
    {
        return true;
    }

    public function getEntityExplain($entity)
    {
        if (!$entity instanceof \Xfrocks\Api\Entity\Log) {
            return parent::getEntityExplain($entity);
        }

        return sprintf('%s - %s', $entity->client_id, $entity->ip_address);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionView(ParameterBag $paramBag)
    {
        $log = $this->assertRecordExists('Xfrocks\Api:Log', $paramBag->log_id);

        return $this->view('Xfrocks\Api:Logs\View', 'bdapi_log_view', [
            'log' => $log
        ]);
    }
}
