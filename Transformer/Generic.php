<?php

namespace Xfrocks\Api\Transformer;

class Generic extends AbstractHandler
{
    public function transformEntity()
    {
        $data = [];
        $entity = $this->entity;
        $structure = $entity->structure();

        $primaryKey = $structure->primaryKey;
        if (is_string($primaryKey)) {
            $data[$primaryKey] = $entity->get($primaryKey);
        }

        return $data;
    }
}
