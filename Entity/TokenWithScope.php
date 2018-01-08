<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Listener;

/**
 * @property array scopes
 */
abstract class TokenWithScope extends Entity
{
    /**
     * @return string
     */
    abstract public function getText();

    /**
     * @param string $newScope
     * @return bool
     */
    public function associateScope($newScope)
    {
        $existingScopes = $this->scopes;
        if (in_array($newScope, $existingScopes, true)) {
            return false;
        }

        $scopes = $existingScopes + [$newScope];
        sort($scopes);

        $this->set('scope', implode(Listener::$scopeDelimiter, $scopes));
        return true;
    }

    /**
     * @return array
     */
    public function getScopes()
    {
        return array_map('trim', preg_split('#\s#', $this->scope, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * @param string $scope
     * @return bool
     */
    public function hasScope($scope)
    {
        $scopes = $this->scopes;
        return in_array($scope, $scopes, true);
    }

    /**
     * @param array $scopes
     */
    public function setScopes($scopes)
    {
        if (!is_array($scopes)) {
            throw new \InvalidArgumentException('Scopes must be an array');
        }

        $this->set('scope', implode(' ', $scopes));

        unset($this->_getterCache['scopes']);
    }
}
