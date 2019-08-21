<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Util\PageNav;

class Tag extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->tag_id > 0) {
            return $this->actionSingle($params->tag_id);
        }

        $tagCloud = $this->options()->tagCloud;
        $tags = [];
        if ((bool) $tagCloud['enabled']) {
            $results = $this->tagRepo()->getTagsForCloud($tagCloud['count'], $this->options()->tagCloudMinUses);
            foreach ($results as $result) {
                $tags[] = $this->transformEntityLazily($result);
            }
        }

        $data = [
            'tags' => $tags
        ];

        return $this->api($data);
    }

    public function actionSingle($tagId)
    {
        $params = $this->params()
              ->definePageNav();

        /** @var \XF\Entity\Tag|null $tag */
        $tag = $this->em()->find('XF:Tag', $tagId);
        if ($tag === null) {
            return $this->error(\XF::phrase('requested_tag_not_found'), 404);
        }

        list($perPage, $page) = $params->filterLimitAndPage();

        $cache = $this->tagRepo()->getTagResultCache($tag->tag_id, 0);
        if ($cache->result_cache_id > 0) {
            $contentTags = $cache->results;
        } else {
            $contentTags = $this->tagRepo()->getTagSearchResults($tag->tag_id, $this->options()->maximumSearchResults);

            $insertCache = count($contentTags) > $perPage;
            if ($insertCache) {
                $cache->results = $contentTags;
                $cache->save();
            }
        }

        $totalResults = count($contentTags);
        $resultSet = $this->tagRepo()->getTagResultSet($contentTags);

        $resultSet->sliceResultsToPage($page, $perPage, false);
        if (!$resultSet->countResults()) {
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        /** @var \Xfrocks\Api\ControllerPlugin\Search $searchPlugin */
        $searchPlugin = $this->plugin('Xfrocks\Api:Search');
        $contentData = $searchPlugin->prepareSearchResults($resultSet->getResults());

        $data = [
            'tag' => $this->transformEntityLazily($tag),
            'tagged' => array_values($contentData),
            'tagged_total' => $totalResults
        ];

        PageNav::addLinksToData($data, $params, $totalResults, 'tags', $tag);

        return $this->api($data);
    }

    public function actionGetFind()
    {
        $params = $this->params()
            ->define('q', 'str');
        $this->assertApiScope(Server::SCOPE_POST);

        $q = $this->tagRepo()->normalizeTag($params['q']);

        $ids = [];
        $tags = [];
        if (strlen($q) >= 2) {
            $results = $this->tagRepo()->getTagAutoCompleteResults($q);
            foreach ($results as $result) {
                $tagTransformed = $this->transformEntityLazily($result)->transform();

                $ids[] = $tagTransformed['tag_id'];
                $tags[] = $tagTransformed;
            }
        }

        $data = [
            'ids' => [],
            'tags' => []
        ];
        return $this->api($data);
    }

    public function actionGetList()
    {
        $params = $this->params()
            ->definePageNav();
        $this->assertValidToken();

        /** @var \XF\Entity\Tag|null $latestTag */
        $latestTag = $this->finder('XF:Tag')
            ->order('tag_id', 'desc')
            ->fetchOne();
        $total = ($latestTag !== null) ? $latestTag->tag_id : 0;

        list($perPage, $page) = $params->filterLimitAndPage();

        $tagIdEnd = $page * $perPage;
        $tagIdStart = $tagIdEnd - $perPage + 1;

        $tags = $this->finder('XF:Tag')
            ->where('tag_id', 'BETWEEN', [$tagIdStart, $tagIdEnd]);

        $data = [
            'tags' => $this->transformFinderLazily($tags),
            'tags_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'tags/list');
        return $this->api($data);
    }

    /**
     * @return \XF\Repository\Tag
     */
      protected function tagRepo()
      {
          /** @var \XF\Repository\Tag $tagRepo */
          $tagRepo = $this->repository('XF:Tag');

          return $tagRepo;
      }
}
