<?php

namespace Xfrocks\Api\Finder;

use Xfrocks\Api\Admin\Controller\Entity;

class AuthCode extends \XF\Mvc\Entity\Finder
{
    /**
     * @param Entity $controller
     * @param array $filters
     * @return array
     */
    public function entityDoListData($controller, array $filters)
    {
        $this->with(['Client', 'User']);
        $this->setDefaultOrder('auth_code_id', 'desc');

        return $filters;
    }

    /**
     * @param string $match
     * @param bool $prefixMatch
     * @return AuthCode
     */
    public function entityDoXfFilter($match, $prefixMatch = false)
    {
        if (strlen($match) > 0) {
            $this->where(
                $this->columnUtf8('auth_code_text'),
                'LIKE',
                $this->escapeLike($match, $prefixMatch ? '?%' : '%?%')
            );
        }

        return $this;
    }
}
