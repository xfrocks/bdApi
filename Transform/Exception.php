<?php

namespace Xfrocks\Api\Transform;

class Exception extends AbstractHandler
{
    const DYNAMIC_KEY_CODE = 'code';
    const DYNAMIC_KEY_MESSAGE = 'message';
    const DYNAMIC_KEY_TRACE = 'trace';

    public function calculateDynamicValue($key)
    {
        /** @var \Exception $exception */
        $exception = $this->source;

        switch ($key) {
            case self::DYNAMIC_KEY_CODE:
                return $exception->getCode();
            case self::DYNAMIC_KEY_MESSAGE:
                return $exception->getMessage();
            case self::DYNAMIC_KEY_TRACE:
                if (!\XF::$debugMode) {
                    return null;
                }

                return explode("\n", $exception->getTraceAsString());
        }

        return null;
    }

    public function getMappings()
    {
        return [
            self::DYNAMIC_KEY_CODE,
            self::DYNAMIC_KEY_MESSAGE,
            self::DYNAMIC_KEY_TRACE,
        ];
    }
}
