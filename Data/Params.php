<?php

namespace Xfrocks\Api\Data;

use Xfrocks\Api\Controller\AbstractController;

class Params implements \ArrayAccess
{
    /**
     * @var AbstractController
     */
    protected $controller;

    /**
     * @var bool
     */
    protected $defineCompleted = false;

    /**
     * @var array
     */
    protected $filtered = [];

    /**
     * @var string
     */
    protected $paramKeyLimit;

    /**
     * @var string
     */
    protected $paramKeyPage;

    /**
     * @var string
     */
    protected $paramKeyTransformSelectorExclude;

    /**
     * @var string
     */
    protected $paramKeyTransformSelectorInclude;

    /**
     * @var Param[]
     */
    protected $params = [];

    /**
     * @param AbstractController $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;

        $this->defineFieldsFiltering();
    }

    /**
     * @param string|Param $key
     * @param string $type
     * @param string $description
     * @return Params
     */
    public function define($key, $type = null, $description = null)
    {
        if ($this->defineCompleted) {
            throw new \LogicException('All params must be defined together and before the first param parsing.');
        }

        if (is_string($key)) {
            $param = new Param($key, $type, $description);
        } else {
            $param = $key;
        }

        $this->params[$param->getKey()] = $param;
        return $this;
    }

    /**
     * @param array $choices
     * @return Params
     */
    public function defineOrder(array $choices)
    {
        $orderParam = new Param('order', 'str', implode(', ', array_keys($choices)));
        return $this->define($orderParam->withDefault('natural'));
    }

    /**
     * @param string $paramKeyLimit
     * @param string $paramKeyPage
     * @return Params
     */
    public function definePageNav($paramKeyLimit = 'limit', $paramKeyPage = 'page')
    {
        $this->paramKeyLimit = $paramKeyLimit;
        $this->paramKeyPage = $paramKeyPage;

        return $this->define($paramKeyLimit, 'posint', 'number of items per page')
            ->define($paramKeyPage, 'posint', 'page number');
    }

    /**
     * @param string $paramKeyExclude
     * @param string $paramKeyInclude
     * @return Params
     */
    public function defineFieldsFiltering($paramKeyExclude = 'fields_exclude', $paramKeyInclude = 'fields_include')
    {
        $this->paramKeyTransformSelectorExclude = $paramKeyExclude;
        $this->paramKeyTransformSelectorInclude = $paramKeyInclude;

        return $this->define($paramKeyExclude, 'str', 'coma-separated list of fields to exclude from the response')
            ->define($paramKeyInclude, 'str', 'coma-separated list of fields to include in the response');
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function filter($key)
    {
        if (!isset($this->params[$key])) {
            throw new \LogicException('Unrecognized parameter: ' . $key);
        }

        if ($key === $this->paramKeyLimit) {
            list($limit,) = $this->filterLimitAndPage();
            return $limit;
        }
        if ($key === $this->paramKeyPage) {
            list(, $page) = $this->filterLimitAndPage();
            return $page;
        }

        if (!isset($this->filtered[$key])) {
            $value = $this->params[$key]->filter($this->controller);
            $this->filtered[$key] = ['value' => $value];
        }

        return $this->filtered[$key]['value'];
    }

    /**
     * @param string $key
     * @return int[]
     */
    public function filterCommaSeparatedIds($key)
    {
        $str = $this->filter($key);
        if (!is_string($str)) {
            return [];
        }

        $ids = preg_split('/[^0-9]/', $str, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($ids)) {
            return [];
        }

        return array_map('intval', $ids);
    }

    /**
     * @return array
     */
    public function filterLimitAndPage()
    {
        if (empty($this->paramKeyLimit) || empty($this->paramKeyPage)) {
            throw new \LogicException('Params::definePageNav() must be called before calling filterLimitAndPage().');
        }

        if (isset($this->filtered[$this->paramKeyLimit]) && isset($this->filtered[$this->paramKeyPage])) {
            return [
                $this->filtered[$this->paramKeyLimit]['value'],
                $this->filtered[$this->paramKeyPage]['value']
            ];
        }

        $controller = $this->controller;
        $options = $controller->options();
        $request = $controller->request();
        $limitDefault = $options->bdApi_paramLimitDefault;
        $limit = $limitDefault;
        $limitInput = $request->filter($this->paramKeyLimit, 'str');
        if (strlen($limitInput) > 0) {
            $limit = intval($limitInput);

            $limitMax = $options->bdApi_paramLimitMax;
            if ($limitMax > 0) {
                $limit = min($limitMax, $limit);
            }
        }
        $limit = max(1, $limit);
        $this->filtered[$this->paramKeyLimit] = [
            'key' => $this->paramKeyLimit,
            'default' => $limitDefault,
            'value' => $limit,
        ];

        $page = $request->filter($this->paramKeyPage, 'posint');
        $pageMax = $options->bdApi_paramPageMax;
        if ($pageMax > 0) {
            $page = min($pageMax, $page);
        }
        $page = max(1, $page);
        $this->filtered[$this->paramKeyPage] = [
            'key' => $this->paramKeyPage,
            'max' => $pageMax,
            'value' => $page
        ];

        return [$limit, $page];
    }

    /**
     * @return array
     */
    public function filterTransformSelector()
    {
        if (empty($this->paramKeyTransformSelectorExclude) ||
            empty($this->paramKeyTransformSelectorInclude)) {
            throw new \LogicException('Params::defineFieldsFiltering() must be called before calling filterTransformSelector().');
        }

        return [
            $this->filter($this->paramKeyTransformSelectorExclude),
            $this->filter($this->paramKeyTransformSelectorInclude),
        ];
    }

    /**
     * @return AbstractController
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param string $key
     * @return array|null
     */
    public function getFiltered($key)
    {
        if (!isset($this->filtered[$key])) {
            return null;
        }
        return $this->filtered[$key];
    }

    /**
     * @return array|null
     */
    public function getFilteredLimit()
    {
        if (!is_string($this->paramKeyLimit)) {
            return null;
        }
        return $this->getFiltered($this->paramKeyLimit);
    }

    /**
     * @return array|null
     */
    public function getFilteredPage()
    {
        if (!is_string($this->paramKeyPage)) {
            return null;
        }
        return $this->getFiltered($this->paramKeyPage);
    }

    /**
     * @return array
     */
    public function getFilteredValues()
    {
        $values = [];
        foreach ($this->filtered as $key => $filtered) {
            $values[$key] = $filtered['value'];
        }
        return $values;
    }

    /**
     * @param \XF\Mvc\Entity\Finder $finder
     * @return int
     */
    public function limitFinderByPage($finder)
    {
        list($limit, $page) = $this->filterLimitAndPage();
        $finder->limitByPage($page, $limit);

        return $page;
    }

    public function offsetExists($offset)
    {
        return isset($this->params[$offset]);
    }

    public function offsetGet($offset)
    {
        if (!$this->defineCompleted) {
            $this->setDefineCompleted();
        }

        return $this->params[$offset]->filter($this->controller);
    }

    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Params::define() must be used to define new param.');
    }

    public function offsetUnset($offset)
    {
        throw new \LogicException('Params::define() must be used to define new param.');
    }

    protected function setDefineCompleted()
    {
        $this->defineCompleted = true;
    }
}
