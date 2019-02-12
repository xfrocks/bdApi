<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class ConversationUser extends AbstractHandler
{
    public function canView(TransformContext $context)
    {
        return true;
    }

    public function onTransformEntities(TransformContext $context, $entities)
    {
        $this->callOnTransformEntitiesForRelation($context, $entities, null, 'Master');

        return parent::onTransformEntities($context, $entities);
    }

    public function onTransformFinder(TransformContext $context, \XF\Mvc\Entity\Finder $finder)
    {
        $this->callOnTransformFinderForRelation($context, $finder, null, 'Master');

        return parent::onTransformFinder($context, $finder);
    }

    public function onTransformed(TransformContext $context, array &$data)
    {
        parent::onTransformed($context, $data);

        $data += $this->transformer->transformEntityRelation($context, null, $context->getSource(), 'Master');
    }
}
