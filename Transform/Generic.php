<?php

namespace Xfrocks\Api\Transform;

use XF\Mvc\Entity\Entity;

class Generic extends AbstractHandler
{
    public function calculateDynamicValue($key)
    {
        if (!isset($this->source[$key])) {
            return null;
        }

        $value = $this->source[$key];
        if (!is_array($value) || !is_callable($value)) {
            return $value;
        }

        return call_user_func($value, $this, $key);
    }

    public function getMappings()
    {
        $mappings = [];

        $source = $this->source;
        if ($source instanceof Entity) {
            $primaryKey = $source->structure()->primaryKey;
            if (is_string($primaryKey)) {
                $mappings[$primaryKey] = $primaryKey;
            } elseif (is_array($primaryKey)) {
                foreach ($primaryKey as $column) {
                    if (is_string($column)) {
                        $mappings[$column] = $column;
                    }
                }
            }
        } elseif (is_array($source)) {
            foreach (array_keys($source) as $key) {
                if (is_array($source[$key])) {
                    $mappings[] = $key;
                } else {
                    $mappings[$key] = $key;
                }
            }
        }

        return $mappings;
    }
}
