<?php

namespace Xfrocks\Api\Util;

class BackwardCompat21
{
    /**
     * @param mixed $entity
     * @param mixed $error
     * @return bool
     */
    public static function canLike($entity, &$error = null)
    {
        $args = [&$error];

        $canLike = [$entity, \XF::$versionId < 2010000 ? 'canLike' : 'canReact'];
        if (is_callable($canLike)) {
            return call_user_func_array($canLike, $args);
        }

        return false;
    }

    /**
     * @return string
     */
    public static function getLikerRelation()
    {
        return \XF::$versionId < 2010000 ? 'Liker' : 'ReactionUser';
    }

    /**
     * @return string
     */
    public static function getLikesColumn()
    {
        return \XF::$versionId < 2010000 ? 'likes' : 'reaction_score';
    }

    /**
     * @return string
     */
    public static function getLikesRelation()
    {
        return \XF::$versionId < 2010000 ? 'Likes' : 'Reactions';
    }

    /**
     * @param mixed $entity
     * @return bool
     */
    public static function isLiked($entity)
    {
        $isLiked = [$entity, \XF::$versionId < 2010000 ? 'isLiked' : 'isReactedTo'];
        if (is_callable($isLiked)) {
            return call_user_func($isLiked);
        }

        return false;
    }
}
