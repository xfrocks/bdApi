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

    /**
     * @param Params $input
     * @param string $contentType
     * @param array $constraints
     * @param array $options
     * @return \XF\Entity\Search|null
     * @throws PrintableException
     */
    public function search(Params $input, $contentType = '', array $constraints = [], array $options = [])
    {
        $httpRequest = new Request($this->app()->inputFilterer(), $input->getFilteredValues());

        $searcher = $this->app()->search();
        $query = $searcher->getQuery();

        if ($contentType !== '') {
            $typeHandler = $searcher->handler($contentType);
            $urlConstraints = [];

            $query->forTypeHandler($typeHandler, $httpRequest, $urlConstraints);
        }

        if (isset($input['q']) && strlen($input['q']) > 0) {
            $query->withKeywords($input['q']);
        }

        if (isset($input['user_id']) && $input['user_id'] > 0) {
            $query->byUserId($input['user_id']);
        }

        if (isset($options[self::OPTION_SEARCH_TYPE])) {
            $query->inType($options[self::OPTION_SEARCH_TYPE]);
        }

        if (isset($input['forum_id']) && $input['forum_id'] > 0) {
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

        if (isset($input['thread_id']) && $input['thread_id'] > 0) {
            $query->withMetadata('thread', $input['thread_id'])
                ->inTitleOnly(false);
        }

        if ($query->getErrors()) {
            $errors = $query->getErrors();

            throw new PrintableException(reset($errors));
        }

        /** @var \XF\Repository\Search $xfSearchRepo */
        $xfSearchRepo = $this->repository('XF:Search');
        /** @var \XF\Entity\Search|null $search */
        $search = $xfSearchRepo->runSearch($query, $constraints);

        return $search;
    }
}
