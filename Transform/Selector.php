<?php

namespace Xfrocks\Api\Transform;

class Selector
{
    const ACTION_EXCLUDE = 'exclude';
    const ACTION_INCLUDE = 'include';
    const ACTION_NONE = 'none';

    /**
     * @var string
     */
    protected $defaultAction = self::ACTION_NONE;

    /**
     * @var array
     */
    protected $rules = [];

    /**
     * @return bool
     */
    public function hasRules()
    {
        if ($this->defaultAction !== self::ACTION_NONE) {
            return true;
        }

        if (count($this->rules) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param string $key
     * @return Selector|null
     */
    public function getSubSelector($key)
    {
        if (!isset($this->rules[$key])) {
            return null;
        }

        $rulesRef =& $this->rules[$key];
        if (isset($rulesRef['selector'])) {
            return $rulesRef['selector'];
        }

        $selector = new self();
        $selector->parseRules($rulesRef['excludes'], $rulesRef['includes']);
        $rulesRef['selector'] = $selector;

        return $selector;
    }

    /**
     * @param string|array $exclude
     * @param string|array $include
     * @return void
     */
    public function parseRules($exclude, $include)
    {
        $this->rules = [];

        $this->parseRulesInclude($include);
        $this->parseRulesExclude($exclude);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function shouldExcludeField($key)
    {
        if ($this->defaultAction === self::ACTION_EXCLUDE) {
            if (isset($this->rules[$key])) {
                $rulesRef =& $this->rules[$key];
                $action = is_string($rulesRef['action']) ? $rulesRef['action'] : $this->defaultAction;
                if ($action === self::ACTION_INCLUDE || count($rulesRef['includes']) > 0) {
                    return false;
                }
            }

            return true;
        }

        if (isset($this->rules[$key])) {
            $rulesRef =& $this->rules[$key];
            $action = is_string($rulesRef['action']) ? $rulesRef['action'] : $this->defaultAction;
            if ($action === self::ACTION_EXCLUDE && count($rulesRef['includes']) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function shouldIncludeField($key)
    {
        if ($this->defaultAction !== self::ACTION_NONE) {
            return !$this->shouldExcludeField($key);
        }

        if (isset($this->rules[$key])) {
            $rulesRef =& $this->rules[$key];
            $action = is_string($rulesRef['action']) ? $rulesRef['action'] : $this->defaultAction;
            if ($action === self::ACTION_INCLUDE || count($rulesRef['includes']) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $key
     * @return void
     */
    protected function makeSureRuleExists($key)
    {
        if (isset($this->rules[$key])) {
            return;
        }

        $this->rules[$key] = [
            'action' => null,
            'excludes' => [],
            'includes' => [],
        ];
    }

    /**
     * @param string|array $rules
     * @return void
     */
    protected function parseRulesExclude($rules)
    {
        if (!is_array($rules)) {
            $rules = explode(',', $rules);
        }

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }

            $parts = explode('.', $rule, 2);
            $key = $parts[0];
            if ($key === '') {
                continue;
            }

            if ($key === '*') {
                $this->defaultAction = self::ACTION_EXCLUDE;
                continue;
            }

            $this->makeSureRuleExists($key);

            if (isset($parts[1])) {
                $this->rules[$key]['excludes'][] = $parts[1];
            } else {
                $this->rules[$key]['action'] = self::ACTION_EXCLUDE;
            }
        }
    }

    /**
     * @param string|array $rules
     * @return void
     */
    protected function parseRulesInclude($rules)
    {
        if (!is_array($rules)) {
            $rules = explode(',', $rules);
        }

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }

            $parts = explode('.', $rule, 2);
            $key = $parts[0];
            if ($key === '') {
                continue;
            }

            if ($key === '*') {
                $this->defaultAction = self::ACTION_INCLUDE;
                continue;
            }

            if ($this->defaultAction === self::ACTION_NONE) {
                $this->defaultAction = self::ACTION_EXCLUDE;
            }

            $this->makeSureRuleExists($key);
            $this->rules[$key]['action'] = self::ACTION_INCLUDE;

            if (isset($parts[1])) {
                $this->rules[$key]['includes'][] = $parts[1];
            }
        }
    }
}
