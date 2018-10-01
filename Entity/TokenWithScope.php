<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Server;

/**
 * COLUMNS
 * @property string scope
 *
 * GETTERS
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
     * @return string[]
     */
    public function getScopes()
    {
        $scopes = preg_split('#\s#', $this->scope, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($scopes)) {
            return [];
        }

        return array_map('trim', $scopes);
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

        $this->set('scope', implode(Listener::$scopeDelimiter, $scopes));

        unset($this->_getterCache['scopes']);
    }

    protected static function addDefaultTokenElements(Structure $structure)
    {
        $structure->columns['scope'] = ['type' => self::STR, 'default' => Server::SCOPE_READ];
        $structure->getters['scopes'] = true;

        return $structure;
    }
}
