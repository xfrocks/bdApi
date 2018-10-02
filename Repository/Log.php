<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;
use Xfrocks\Api\Transform\LazyTransformer;
use Xfrocks\Api\XF\ApiOnly\Session\Session;

class Log extends Repository
{
    protected static $logging = 1;

    public function pauseLogging()
    {
        self::$logging--;
    }

    public function resumeLogging()
    {
        self::$logging++;
    }

    public function pruneExpired()
    {
        $days = $this->options()->bdApi_logRetentionDays;
        $cutoff = \XF::$time - $days * 86400;

        return $this->db()->delete('xf_bdapi_log', 'request_date < ?', $cutoff);
    }

    public function logRequest(
        $requestMethod,
        $requestUri,
        array $requestData,
        $responseCode,
        array $responseOutput,
        array $bulkSet = []
    ) {
        if (self::$logging < 1) {
            return false;
        }

        $days = $this->options()->bdApi_logRetentionDays;
        if ($days == 0) {
            return false;
        }

        /** @var \Xfrocks\Api\Entity\Log $log */
        $log = $this->em->create('Xfrocks\Api:Log');

        $log->bulkSet($bulkSet);
        if (!isset($bulkSet['client_id'])) {
            /** @var Session $session */
            $session = $this->app()->session();
            $token = $session->getToken();
            $log->client_id = '';

            if ($token) {
                $log->client_id = $token->client_id;
            }
        }

        if (!isset($bulkSet['user_id'])) {
            $log->user_id = \XF::visitor()->user_id;
        }

        if (!isset($bulkSet['ip_address'])) {
            $log->ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }

        $log->request_method = $requestMethod;
        $log->request_uri = $requestUri;
        $log->request_data = $this->filterData($requestData);
        $log->response_code = $responseCode;
        $log->response_output = $this->filterData($responseOutput);

        return $log->save();
    }

    protected function filterData(array &$data, $level = 0)
    {
        $filtered = array();

        foreach ($data as $key => &$value) {
            $keyFirstChar = substr($key, 0, 1);
            if ($keyFirstChar === '.'
                || $keyFirstChar === '_'
            ) {
                continue;
            }

            if (is_array($value)) {
                if ($level < 3) {
                    $filtered[$key] = $this->filterData($value, $level + 1);
                } else {
                    $filtered[$key] = '(array)';
                }
            } else {
                if (is_bool($value)) {
                    $filtered[$key] = $value;
                } elseif (is_numeric($value)) {
                    $filtered[$key] = $value;
                } elseif (is_string($value)) {
                    $filtered[$key] = $value;
                } elseif ($value instanceof LazyTransformer) {
                    $filtered[$key] = $value->getLogData();
                } else {
                    $filtered[$key] = '?';
                }
            }
        }

        return $filtered;
    }
}
