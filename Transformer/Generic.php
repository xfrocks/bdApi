<?php

namespace Xfrocks\Api\Transformer;

class Generic extends AbstractHandler
{
    public function getMappings()
    {
        $mappings = [];

        $primaryKey = $this->entity->structure()->primaryKey;
        if (is_string($primaryKey)) {
            $mappings[$primaryKey] = $primaryKey;
        } elseif (is_array($primaryKey)) {
            foreach ($primaryKey as $column) {
                if (is_string($column)) {
                    $mappings[$column] = $column;
                }
            }
        }

        return $mappings;
    }
}
