<?php

namespace Xfrocks\Api\Transform;

class Exception extends AbstractHandler
{
    const DYNAMIC_KEY_CODE = 'code';
    const DYNAMIC_KEY_MESSAGE = 'message';
    const DYNAMIC_KEY_TRACE = 'trace';

    public function calculateDynamicValue($context, $key)
    {
        /** @var \Exception $exception */
        $exception = $context->source;

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

    public function getMappings($context)
    {
        return [
            self::DYNAMIC_KEY_CODE,
            self::DYNAMIC_KEY_MESSAGE,
            self::DYNAMIC_KEY_TRACE,
        ];
    }
}
