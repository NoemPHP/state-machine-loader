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

    private function isService(string $defined): bool
    {
        return str_starts_with($defined, '@');
    }
}
