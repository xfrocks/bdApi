<?php

namespace Xfrocks\Api\Util;

class Tree
{
    /**
     * @param \XF\Tree $tree
     * @param array $ids
     * @return array
     */
    public static function getAllChildIds($tree, array $ids)
    {
        $i = 0;
        while ($i < count($ids)) {
            $childIds = $tree->childIds($ids[$i]);
            $i++;

            if (count($childIds) === 0) {
                continue;
            }

            $ids = array_merge($ids, $childIds);
        }

        return $ids;
    }
}
