<?php

namespace Xfrocks\Api\XFRM\Controller;

class Search extends XFCP_Search
{
    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Reroute
     * @throws \XF\PrintableException
     */
    public function actionPostResources()
    {
        if (!\XF::visitor()->canSearch($error)) {
            return $this->noPermission($error);
        }

        $params = $this
            ->params()
            ->define('q', 'str', 'query to search for')
            ->define('user_id', 'uint', 'id of the creator to search for contents');

        if ($params['q'] === '') {
            return $this->error(\XF::phrase('bdapi_slash_search_requires_q'), 400);
        }

        $search = $this->searchRepo()->search($params, 'resource');
        if ($search === null) {
            // no results.
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        return $this->rerouteController(__CLASS__, 'getResults', ['search_id' => $search->search_id]);
    }
}
