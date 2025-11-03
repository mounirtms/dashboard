<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Traits;

trait AsArrayTrait
{
    /**
     * @return array
     * @throws \ReflectionException
     */
    public function asArray(): array
    {
        $data              = [];
        $restrictedGetters = [
            'getCustomAttribute',
            'getIterator'
        ];

        $reflectionClass = new \ReflectionClass($this);
        $publicMethods   = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);

        $getterMethods = array_filter(
            $publicMethods,
            function ($publicMethod) use ($restrictedGetters) {
                return stripos($publicMethod->name, 'get') === 0
                    && !in_array($publicMethod->name, $restrictedGetters)
                    && $publicMethod->getNumberOfParameters() < 1;
            }
        );

        foreach ($getterMethods as $method) {
            $keyInCamelCase = substr($method->name, 3);
            $keyInSnakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $keyInCamelCase));
            $result         = $method->invoke($this);
            if ($result !== null) {
                $data[$keyInSnakeCase] = $result;
            }
        }

        return $data;
    }
}
