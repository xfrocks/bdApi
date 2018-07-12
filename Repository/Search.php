<?php

namespace Xfrocks\Api\Repository;

use XF\Entity\Forum;
use XF\Http\Request;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Repository\Node;
use Xfrocks\Api\Data\Params;

class Search extends Repository
{
    const OPTION_SEARCH_TYPE = 'searchType';

    public function search(Params $input, $contentType = '', array $constraints = [], array $options = [])
    {
        $httpRequest = new Request($this->app()->inputFilterer(), $input->getFilteredValues());

        $searcher = $this->app()->search();
        $query = $searcher->getQuery();

        if (!empty($contentType)) {
            $typeHandler = $searcher->handler($contentType);
            $urlConstraints = [];

            $query->forTypeHandler($typeHandler, $httpRequest, $urlConstraints);
        }

        if (!empty($input['q'])) {
            $query->withKeywords($input['q']);
        }

        if (!empty($input['user_id'])) {
            $query->byUserId($input['user_id']);
        }

        if (!empty($options[self::OPTION_SEARCH_TYPE])) {
            $query->inType($options[self::OPTION_SEARCH_TYPE]);
        }

        if (!empty($input['forum_id'])) {
            /** @var Node $nodeRepo */
            $nodeRepo = $this->repository('XF:Node');
            /** @var Forum|null $forum */
            $forum = $this->em->find('XF:Forum', $input['forum_id']);
            $nodeIds = [];

            if ($forum) {
                $children = $nodeRepo->findChildren($forum->Node, false)->fetch();

                $nodeIds = $children->keys();
                $nodeIds[] = $forum->node_id;
            }


            $query->withMetadata('node', $nodeIds ?: $input['forum_id']);
        }

        if (!empty($input['thread_id'])) {
            $query->withMetadata('thread', $input['thread_id'])
                  ->inTitleOnly(false);
        }

        if ($query->getErrors()) {
            $errors = $query->getErrors();

            throw new PrintableException(reset($errors));
        }

        /** @var \XF\Repository\Search $xfSearchRepo */
        $xfSearchRepo = $this->repository('XF:Search');
        $search = $xfSearchRepo->runSearch($query, $constraints);

        return $search;
    }
}
