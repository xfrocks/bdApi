<?php

namespace dev\PHPStan\Reflection;

use DevHelper\PHPStan\Reflection\EntityColumnReflection;
use DevHelper\PHPStan\Reflection\EntityGetterReflection;
use DevHelper\PHPStan\Reflection\EntityRelationReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use XF\Mvc\Entity\Entity;

class PropertiesClassReflectionExtension implements \PHPStan\Reflection\PropertiesClassReflectionExtension
{
    private $map = [
        'XF\Entity\AbstractNode' => [
            'node_id' => ['column', 'type' => Entity::UINT],
            'Node' => ['relation', 'type' => Entity::TO_ONE, 'entity' => 'XF:Node'],
        ],
        'Xfrocks\Api\Entity\TokenWithScope' => [
            'scope' => ['column', 'type' => Entity::STR],
            'scopes' => ['getter', 'methodName' => 'getScopes'],
        ],
    ];

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        $className = $classReflection->getName();
        if (!isset($this->map[$className])) {
            return false;
        }

        $classMap = $this->map[$className];
        if (!isset($classMap[$propertyName])) {
            return false;
        }

        return true;
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $mapEntry = $this->map[$classReflection->getName()][$propertyName];

        switch ($mapEntry[0]) {
            case 'column':
                return new EntityColumnReflection($classReflection, $mapEntry['type']);
            case 'getter':
                if ($classReflection->hasNativeMethod($mapEntry['methodName'])) {
                    $method = $classReflection->getNativeMethod($mapEntry['methodName']);
                    return new EntityGetterReflection(
                        $classReflection,
                        $method->getVariants()[0]->getReturnType()
                    );
                }
                break;
            case 'relation':
                return new EntityRelationReflection($classReflection, $mapEntry['type'], $mapEntry['entity']);
        }

        throw new \PHPStan\ShouldNotHappenException();
    }
}
