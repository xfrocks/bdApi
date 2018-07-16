<?php

namespace Xfrocks\Api\DevHelper\PHPStan\Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;

abstract class TokenWithScope extends \Xfrocks\Api\Entity\TokenWithScope
{
    public static function getStructure(Structure $structure)
    {
        parent::addDefaultTokenElements($structure);

        return $structure;
    }
}
