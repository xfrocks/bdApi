<?php

namespace Xfrocks\Api\Cron;

use XF\Util\File;
use Xfrocks\Api\Repository\AuthCode;
use Xfrocks\Api\Repository\RefreshToken;
use Xfrocks\Api\Repository\Token;

class CleanUp
{
    public static function runHourlyCleanUp()
    {
        $app = \XF::app();
        $cleanedUp = [];

        /** @var AuthCode $authCodeRepo */
        $authCodeRepo = $app->repository('Xfrocks\Api:AuthCode');
        $cleanedUp['authCodes'] = $authCodeRepo->deleteExpiredAuthCodes();

        /** @var RefreshToken $refreshTokenRepo */
        $refreshTokenRepo = $app->repository('Xfrocks\Api:RefreshToken');
        $cleanedUp['refreshTokens'] = $refreshTokenRepo->deleteExpiredRefreshTokens();

        /** @var Token $tokenRepo */
        $tokenRepo = $app->repository('Xfrocks\Api:Token');
        $cleanedUp['tokens'] = $tokenRepo->deleteExpiredTokens();

        if (\XF::$debugMode) {
            $json = json_encode($cleanedUp);
            if (is_string($json)) {
                File::log(__CLASS__, $json);
            }
        }
    }
}
