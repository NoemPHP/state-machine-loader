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
            return $serviceLocator->get(substr($serviceDefinition, 1));
        }
        throw new InvalidSchemaException([$serviceDefinition => "Could not resolve event handler"]);
    }

    private function isService(string $defined): bool
    {
        return str_starts_with($defined, '@');
    }
}
