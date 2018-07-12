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
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $namedSelectors = [];

    /**
     * @var array|null
     */
    protected $rules = null;

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;

        if (strlen($name) > 0) {
            $this->namedSelectors[$name] = $this;
        }
    }

    /**
     * @param string $key
     * @return Selector
     */
    public function getSubSelector($key)
    {
        if (isset($this->rules[$key])) {
            $rulesRef =& $this->rules[$key];
            if (isset($rulesRef['selector'])) {
                return $rulesRef['selector'];
            }

            $selector = $this->getSubSelectorNew();
            $selector->parseRules($rulesRef['excludes'], $rulesRef['includes']);
            $rulesRef['selector'] = $selector;

            return $selector;
        }

        $this->makeSureRuleExists($key);
        $selector = $this->getSubSelectorNew();
        $this->rules[$key]['selector'] = $selector;
        return $selector;
    }

    /**
     * @return Selector
     */
    public function getSubSelectorNew()
    {
        $selector = new Selector('');

        foreach ($this->namedSelectors as $key => $namedSelector) {
            $selector->namedSelectors[$key] = $namedSelector;
        }

        return $selector;
    }

    /**
     * @param string $key
     * @param string $name
     * @return Selector
     */
    public function getSubSelectorNamed($key, $name)
    {
        if (isset($this->rules[$key])) {
            $selector = $this->getSubSelector($key);
            if ($selector !== null) {
                return $selector;
            }
        }

        if (isset($this->namedSelectors[$name])) {
            return $this->namedSelectors[$name];
        }

        return $this->getSubSelector($key);
    }

    /**
     * @return bool
     */
    public function hasParsedRules()
    {
        return is_array($this->rules);
    }

    /**
     * @param string|array $exclude
     * @param string|array $include
     */
    public function parseRules($exclude, $include)
    {
        $this->rules = [];

        if (!empty($include)) {
            $this->parseRulesInclude($include);
        }

        if (!empty($exclude)) {
            $this->parseRulesExclude($exclude);
        }
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
                $action = $rulesRef['action'] ?: $this->defaultAction;
                if ($action === self::ACTION_INCLUDE) {
                    return false;
                }
                if (!empty($rulesRef['includes'])) {
                    return false;
                }
            }

            return true;
        }

        if (isset($this->rules[$key])) {
            $rulesRef =& $this->rules[$key];
            $action = $rulesRef['action'] ?: $this->defaultAction;
            if ($action === self::ACTION_EXCLUDE &&
                empty($rulesRef['includes'])) {
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
            $action = $rulesRef['action'] ?: $this->defaultAction;
            if ($action === self::ACTION_INCLUDE) {
                return true;
            }
            if (!empty($rulesRef['includes'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $key
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
     */
    protected function parseRulesExclude($rules)
    {
        if (!is_array($rules)) {
            $rules = explode(',', $rules);
        }

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (empty($rule)) {
                continue;
            }

            $parts = explode('.', $rule, 2);
            $key = $parts[0];
            if (empty($key)) {
                continue;
            }

            if ($key === '*') {
                $this->defaultAction = self::ACTION_EXCLUDE;
                continue;
            }

            $this->makeSureRuleExists($key);


            if (!empty($parts[1])) {
                $this->rules[$key]['excludes'][] = $parts[1];
            } else {
                $this->rules[$key]['action'] = self::ACTION_EXCLUDE;
            }
        }
    }

    /**
     * @param string|array $rules
     */
    protected function parseRulesInclude($rules)
    {
        if (!is_array($rules)) {
            $rules = explode(',', $rules);
        }

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (empty($rule)) {
                continue;
            }

            $parts = explode('.', $rule, 2);
            $key = $parts[0];
            if (empty($key)) {
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

            if (!empty($parts[1])) {
                $this->rules[$key]['includes'][] = $parts[1];
            }
        }
    }
}
