<?php

namespace Xfrocks\Api\DevHelper\PHPStan\XF\Entity;

use XF\Mvc\Entity\Structure;

abstract class AbstractNode extends \XF\Entity\AbstractNode
{
    public static function getStructure(Structure $structure)
    {
        parent::addDefaultNodeElements($structure);

        // TODO: remove this when parent starts handling by itself
        $structure->columns['node_id'] = ['type' => self::UINT];

        return $structure;
    }
}
