<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\Reply\AbstractReply;
use Xfrocks\Api\Data\BatchJob;
use Xfrocks\Api\Transformer;

class Batch extends AbstractController
{
    public function actionPostIndex()
    {
        $inputRaw = $this->request->getInputRaw();
        if (empty($inputRaw) && \XF::$debugMode) {
            $inputRaw = $this->request->filter('_xfApiInputRaw', 'str');
        }
        $configs = @json_decode($inputRaw, true);
        if (empty($configs)) {
            return $this->error(\XF::phrase('bdapi_slash_batch_requires_json'));
        }

        $replies = [];
        foreach ($configs as $config) {
            $job = $this->buildJobFromConfig($config);
            if (empty($job)) {
                continue;
            }

            if (empty($config['id'])) {
                $i = 0;
                do {
                    $id = $i > 0 ? sprintf('%s_%d', $job->getUri(), $i) : $job->getUri();
                    $i++;
                } while (isset($replies[$id]));
            } else {
                $id = $config['id'];
            }

            $replies[$id] = $job->execute();
        }

        $data = [
            'jobs' => $this->transformReplies($replies)
        ];

        return $this->api($data);
    }

    /**
     * @param array $config
     * @return BatchJob|null
     */
    protected function buildJobFromConfig($config)
    {
        if (!is_array($config)) {
            return null;
        }
        if (empty($config['uri'])) {
            return null;
        }

        if (empty($config['method'])) {
            $config['method'] = 'GET';
        }
        $config['method'] = strtoupper($config['method']);

        if (empty($config['params']) || !is_array($config['params'])) {
            $config['params'] = [];
        }

        $uriQuery = @parse_url($config['uri'], PHP_URL_QUERY);
        if (!empty($uriQuery)) {
            $uriParams = [];
            parse_str($uriQuery, $uriParams);
            if (count($uriParams) > 0) {
                $config['params'] = array_merge($uriParams, $config['params']);
            }
        }

        return new BatchJob($this->app, $config['method'], $config['params'], $config['uri']);
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return null;
    }

    protected function logRequest(AbstractReply $reply, $action)
    {
        // Does not support log request for this controller
    }

    /**
     * @param array $replies
     * @return array
     */
    protected function transformReplies(array $replies)
    {
        $data = [];

        /** @var Transformer $transformer */
        $transformer = $this->app()->container('api.transformer');

        foreach ($replies as $jobId => $reply) {
            $data[$jobId] = $transformer->transformBatchJobReply($reply);
        }

        return $data;
    }
}
