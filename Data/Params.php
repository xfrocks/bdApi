<?php

namespace Xfrocks\Api\Data;

use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Transform\Selector;
use Xfrocks\Api\Transform\TransformContext;

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
     * @var bool
     */
    protected $isDeprecated = false;

    /**
     * @var array[]
     */
    protected $orderChoices = [];

    /**
     * @var string
     */
    protected $paramKeyLimit = '';

    /**
     * @var string
     */
    protected $paramKeyOrder = '';

    /**
     * @var string
     */
    protected $paramKeyPage = '';

    /**
     * @var string
     */
    protected $paramKeyTransformSelectorExclude = '';

    /**
     * @var string
     */
    protected $paramKeyTransformSelectorInclude = '';

    /**
     * @var Param[]
     */
    protected $params = [];

    /**
     * @var TransformContext|null
     */
    protected $transformContext = null;

    /**
     * @param AbstractController $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;

        $this->defineFieldsFiltering();
    }

    /**
     * @param string $key
     * @param string|Param|null $type
     * @param string|null $description
     * @param mixed|null $default
     * @return Params
     */
    public function define($key, $type = null, $description = null, $default = null)
    {
        if ($this->defineCompleted) {
            throw new \LogicException('All params must be defined together and before the first param parsing.');
        }
        if ($key === '') {
            throw new \InvalidArgumentException('$key cannot be an empty string');
        }

        if ($type === null || is_string($type)) {
            $param = new Param($type, $description);
        } else {
            $param = $type;
        }

        if ($default !== null) {
            $param->default = $default;
        }

        $this->params[$key] = $param;
        return $this;
    }

    /**
     * @return $this
     */
    public function defineAttachmentHash()
    {
        $this->define('attachment_hash', 'str', 'a unique hash value');

        return $this;
    }

    /**
     * @param string $key
     * @param string|null $description
     * @return Params
     */
    public function defineFile($key, $description = null)
    {
        return $this->define($key, 'file', $description);
    }

    /**
     * @param string $key
     * @param string|null $description
     * @return Params
     */
    public function defineFiles($key, $description = null)
    {
        return $this->define($key, 'files', $description);
    }

    /**
     * @param array[] $choices
     * @param string $paramKeyOrder
     * @param string $defaultOrder
     * @return Params
     */
    public function defineOrder(array $choices, $paramKeyOrder = 'order', $defaultOrder = 'natural')
    {
        $this->orderChoices = $choices;
        $this->paramKeyOrder = $paramKeyOrder;

        $param = new Param('str', implode(', ', array_keys($choices)));
        $param->default = $defaultOrder;

        return $this->define($paramKeyOrder, $param);
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
    public function defineFieldsFiltering(
        $paramKeyExclude = 'fields_exclude',
        $paramKeyInclude = 'fields_include'
    ) {
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
        if (!$this->defineCompleted) {
            $this->setDefineCompleted();
        }

        if (!isset($this->params[$key])) {
            throw new \LogicException('Unrecognized parameter: ' . $key);
        }
        $param = $this->params[$key];

        if ($key === $this->paramKeyLimit) {
            list($limit,) = $this->filterLimitAndPage();
            return $limit;
        }
        if ($key === $this->paramKeyPage) {
            list(, $page) = $this->filterLimitAndPage();
            return $page;
        }

        if (!isset($this->filtered[$key])) {
            $request = $this->controller->request();

            if ($param->type === 'files' || $param->type === 'file') {
                $valueRaw = null;
                $value = $request->getFile($key, $param->type === 'files', false);
            } else {
                $valueRaw = $request->get($key, $param->default);
                $filterer = $this->controller->app()->inputFilterer();
                $value = $filterer->filter($valueRaw, $param->type, $param->options);
            }

            $this->filtered[$key] = [
                'default' => $param->default,
                'value' => $value,
                'valueRaw' => $valueRaw
            ];
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
        if ($this->paramKeyLimit === '' || $this->paramKeyPage === '') {
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
        $limit = $limitDefault = intval($options->bdApi_paramLimitDefault);
        $limitRaw = $request->get($this->paramKeyLimit, '');
        if (strlen($limitRaw) > 0) {
            $limit = intval($limitRaw);

            $limitMax = intval($options->bdApi_paramLimitMax);
            if ($limitMax > 0) {
                $limit = min($limitMax, $limit);
            }
        }
        $limit = max(1, $limit);
        $this->filtered[$this->paramKeyLimit] = [
            'default' => $limitDefault,
            'key' => $this->paramKeyLimit,
            'value' => $limit,
            'valueRaw' => $limitRaw
        ];

        $pageDefault = '1';
        $pageRaw = $request->get($this->paramKeyPage, $pageDefault);
        $page = intval($pageRaw);
        $pageMax = intval($options->bdApi_paramPageMax);
        if ($pageMax > 0) {
            $page = min($pageMax, $page);
        }
        $page = max(1, $page);
        $this->filtered[$this->paramKeyPage] = [
            'default' => $pageDefault,
            'key' => $this->paramKeyPage,
            'max' => $pageMax,
            'value' => $page,
            'valueRaw' => $pageRaw
        ];

        return [$limit, $page];
    }

    /**
     * @return array
     */
    public function filterTransformSelector()
    {
        if ($this->paramKeyTransformSelectorExclude === '' || $this->paramKeyTransformSelectorInclude === '') {
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
        return $this->getFiltered($this->paramKeyLimit);
    }

    /**
     * @return array|null
     */
    public function getFilteredPage()
    {
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
     * @return TransformContext
     */
    public function getTransformContext()
    {
        if (!$this->transformContext) {
            $selector = new Selector();
            list($exclude, $include) = $this->filterTransformSelector();
            $selector->parseRules($exclude, $include);

            $this->transformContext = new TransformContext(null, null, $selector);
        }

        return $this->transformContext;
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

    /**
     * @return void
     */
    public function markAsDeprecated()
    {
        $this->isDeprecated = true;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->params[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->filter($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Params::define() must be used to define new param.');
    }

    /**
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('Params::define() must be used to define new param.');
    }

    /**
     * @param \XF\Mvc\Entity\Finder $finder
     * @return array|false
     */
    public function sortFinder($finder)
    {
        if ($this->paramKeyOrder === '') {
            throw new \LogicException('Params::defineOrder() must be called before calling sortFinder().');
        }

        $order = $this->offsetGet($this->paramKeyOrder);
        if ($order === '' || !isset($this->orderChoices[$order])) {
            return false;
        }

        $orderChoice = $this->orderChoices[$order];

        if (count($orderChoice) >= 2) {
            $finder->order($orderChoice[0], $orderChoice[1]);
        }

        return $orderChoice;
    }

    /**
     * @return void
     */
    protected function setDefineCompleted()
    {
        $this->defineCompleted = true;
    }
}
