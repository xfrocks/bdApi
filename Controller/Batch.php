<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\Reply\AbstractReply;
use Xfrocks\Api\Data\BatchJob;
use Xfrocks\Api\Transformer;

class Batch extends AbstractController
{
    /**
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionPostIndex()
    {
        $inputRaw = $this->request->getInputRaw();
        if ($inputRaw === '' && \XF::$debugMode) {
            $inputRaw = $this->request->filter('_xfApiInputRaw', 'str');
        }
        $configs = @json_decode($inputRaw, true);
        if (!is_array($configs)) {
            return $this->error(\XF::phrase('bdapi_slash_batch_requires_json'));
        }

        $replies = [];
        foreach ($configs as $config) {
            if (!is_array($config)) {
                continue;
            }
            $job = $this->buildJobFromConfig($config);
            if (!$job) {
                continue;
            }

            if (!isset($config['id'])) {
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
    protected function buildJobFromConfig(array $config)
    {
        if (!isset($config['uri']) || !is_string($config['uri'])) {
            return null;
        }

        if (!isset($config['method'])) {
            $config['method'] = 'GET';
        }
        $config['method'] = strtoupper($config['method']);

        if (!isset($config['params']) || !is_array($config['params'])) {
            $config['params'] = [];
        }

        /** @var string|false $uriQuery */
        $uriQuery = @parse_url($config['uri'], PHP_URL_QUERY);
        if ($uriQuery !== false) {
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

    protected function logRequest(AbstractReply $reply)
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
