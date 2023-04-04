<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\Loader\Exception\InvalidSchemaException;
use Psr\Container\ContainerInterface;

trait ServiceResolverTrait
{

    /**
     * @throws InvalidSchemaException
     */
    private function resolveService($serviceDefinition, ContainerInterface $serviceLocator): callable
    {
        if (is_callable($serviceDefinition)) {
            return $serviceDefinition;
        }
        if (is_array($serviceDefinition)) {
            return $this->resolveArrayDefinition($serviceDefinition, $serviceLocator);
        }
        if ($this->isService($serviceDefinition)) {
            $serviceName = substr($serviceDefinition, 1);
            if (!$serviceLocator->has($serviceName)) {
                throw new InvalidSchemaException([
                    $serviceDefinition => "Service '{$serviceName}' not found in container",
                ]);
            }

            return $serviceLocator->get($serviceName);
        }
        throw new InvalidSchemaException([$serviceDefinition => "Could not resolve event handler"]);
    }

    private function resolveParameter(string $parameter, ContainerInterface $serviceLocator)
    {
        if (!str_starts_with($parameter, '%') && !str_ends_with($parameter, '%')) {
            return $parameter;
        }
        $parameter = trim($parameter, '%');

        return $serviceLocator->get($parameter);
    }

    private function resolveArrayDefinition(
        array $definition,
        ContainerInterface $serviceLocator
    ): callable {
        switch ($definition['type']) {
            case 'factory':
                $factory = $this->resolveService($definition['factory'], $serviceLocator);

                return $factory(
                    ...
                    array_map(
                        fn(string $p) => $this->resolveParameter($p, $serviceLocator),
                        $definition['arguments']
                    )
                );
        }
        throw new InvalidSchemaException(
            [
                'unknown' => "Could not process guard definition",
            ]
        );
    }

    private function isService(string $defined): bool
    {
        return str_starts_with($defined, '@');
    }
}
