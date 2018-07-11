<?php

namespace Xfrocks\Api\Controller;

class Search extends AbstractController
{
    public function actionGetIndex()
    {
        $data = [
            'links' => [
                'posts' => $this->buildApiLink('search/posts'),
                'threads' => $this->buildApiLink('search/threads')
            ]
        ];

        return $this->api($data);
    }
}