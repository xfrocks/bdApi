<?php

namespace Xfrocks\Api\Util;

use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\Entity\Token;

class OneTimeToken
{
    /**
     * @param int $ttl
     * @param Client $client
     * @return string
     */
    public static function generate($ttl, $client)
    {
        $userId = 0;
        $timestamp = time() + $ttl;
        $tokenText = '';
        $once = self::generateOnce($userId, $timestamp, $tokenText, $client->client_secret);
        $ott = sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $client->client_id);

        return $ott;
    }

    /**
     * @param int $userId
     * @param int $timestamp
     * @param string $tokenText
     * @param string $clientSecret
     * @return string
     */
    public static function generateOnce($userId, $timestamp, $tokenText, $clientSecret)
    {
        return md5($userId . $timestamp . $tokenText . $clientSecret);
    }

    /**
     * @param string $ott
     * @return Token|null
     */
    public static function parse($ott)
    {
        if (!preg_match('/^(\d+),(\d+),(.{32}),(.+)$/', $ott, $matches)) {
            return null;
        }

        $userId = intval($matches[1]);
        $timestamp = intval($matches[2]);
        $once = $matches[3];
        $clientId = $matches[4];

        if ($timestamp < time()) {
            return null;
        }

        $app = \XF::app();
        /** @var Token $token */
        $token = $app->em()->create('Xfrocks\Api:Token');
        $token->client_id = $clientId;
        $token->token_text = $ott;
        $token->expire_date = $timestamp;
        $token->user_id = $userId;

        if ($userId === 0 &&
            $once === self::generateOnce($userId, $timestamp, '', $token->Client->client_secret)
        ) {
            return $token;
        }

        /** @var \XF\Repository\User $userRepo */
        $userRepo = $app->repository('XF:User');
        $userWith = $userRepo->getVisitorWith();
        $with = array_map(function ($with) {
            return 'User.' . $with;
        }, $userWith);

        $userTokenTexts = $app->finder('Xfrocks\Api:Token')
            ->where('client_id', $clientId)
            ->where('user_id', $userId)
            ->with($with)
            ->with('Client')
            ->pluckFrom('expire_date', 'token_text')
            ->fetch();

        foreach ($userTokenTexts as $userTokenText => $expireDate) {
            if ($once === self::generateOnce($userId, $timestamp, $userTokenText, $token->Client->client_secret)) {
                $token->expire_date = min($token->expire_date, $expireDate);
                return $token;
            }
        }

        return null;
    }
}
