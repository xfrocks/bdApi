<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class ConversationUser extends AbstractHandler
{
    public function canView($context)
    {
        return true;
    }

    public function onTransformEntities($context, $entities)
    {
        $this->callOnTransformEntitiesForRelation($context, $entities, null, 'Master');

        return parent::onTransformEntities($context, $entities);
    }

    public function onTransformFinder($context, $finder)
    {
        $this->callOnTransformFinderForRelation($context, $finder, null, 'Master');

        return parent::onTransformFinder($context, $finder);
    }

    public function onTransformed($context, array &$data)
    {
        parent::onTransformed($context, $data);

        $data += $this->transformer->transformEntityRelation($context, null, $context->getSource(), 'Master');
    }
}
