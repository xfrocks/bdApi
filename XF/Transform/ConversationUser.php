<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class ConversationUser extends AbstractHandler
{
    public function onTransformed($context, array &$data)
    {
        parent::onTransformed($context, $data);

        $newData = $this->transformer->transformEntityRelation($context, '', $context->getSource(), 'Master');
        $data = array_replace_recursive($data, $newData);
    }
}
