<?php

namespace Xfrocks\Api\Util;

class ParentFinder
{
    /**
     * @var \XF\Mvc\Entity\Finder
     */
    protected $finder;

    /**
     * @var \XF\Mvc\Entity\Finder|null
     */
    protected $parentFinder;

    /**
     * @var string
     */
    protected $relationKey;

    /**
     * @param \XF\Mvc\Entity\Finder $finder
     * @param string $relationKey
     */
    public function __construct($finder, $relationKey)
    {
        $structure = $finder->getStructure();

        if (!isset($structure->relations[$relationKey])) {
            throw new \InvalidArgumentException(
                sprintf('%s does not have relation %s', $structure->shortName, $relationKey)
            );
        }

        $relationConfig = $structure->relations[$relationKey];
        if (!is_array($relationConfig) || !isset($relationConfig['entity'])) {
            throw new \InvalidArgumentException(
                sprintf('Relation %s.%s has invalid config', $structure->shortName, $relationKey)
            );
        }

        $this->finder = $finder;
        $this->parentFinder = self::getParentFinderOfType($finder, $relationConfig['entity']);
        $this->relationKey = $relationKey;
    }

    /**
     * @param string $name
     * @return void
     */
    public function with($name)
    {
        if ($this->parentFinder !== null) {
            $this->parentFinder->with($name);
        } else {
            $this->finder->with(sprintf('%s.%s', $this->relationKey, $name));
        }
    }

    /**
     * @param \XF\Mvc\Entity\Finder $finder
     * @param string $shortName
     * @return \XF\Mvc\Entity\Finder|null
     */
    public static function getParentFinderOfType($finder, $shortName)
    {
        while (true) {
            if ($finder->getStructure()->shortName === $shortName) {
                return $finder;
            }

            $finder = $finder->getParentFinder();
            if (!$finder) {
                break;
            }
        }

        return null;
    }
}
