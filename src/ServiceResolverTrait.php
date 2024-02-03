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
    private function resolveService(
        $serviceDefinition,
        ContainerInterface $serviceLocator,
        CallbackType $callbackType
    ): callable {
        if (is_callable($serviceDefinition)) {
            return $serviceDefinition;
        }
        if (is_array($serviceDefinition)) {
            return $this->resolveArrayDefinition($serviceDefinition, $serviceLocator, $callbackType);
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
        throw new InvalidSchemaException([$serviceDefinition => "Could not resolve event handler {$serviceDefinition}"]);
    }

    private function resolveParameter(string $parameter, ContainerInterface $serviceLocator)
    {
        if (!str_starts_with($parameter, '%') && !str_ends_with($parameter, '%')) {
            return $parameter;
        }
        $parameter = trim($parameter, '%');

        return $serviceLocator->get($parameter);
    }

    /**
     * @throws InvalidSchemaException
     */
    private function resolveArrayDefinition(
        array $definition,
        ContainerInterface $serviceLocator,
        CallbackType $callbackType
    ): callable {
        $callbackDefinitionType = CallbackDefinitonType::from($definition['type']);
        switch ($callbackDefinitionType) {
            case CallbackDefinitonType::Factory:
                $factory = $this->resolveService($definition['factory'], $serviceLocator, $callbackType);

                return $factory(
                    ...
                    array_map(
                        fn(string $p) => $this->resolveParameter($p, $serviceLocator),
                        $definition['arguments']
                    )
                );
            case CallbackDefinitonType::Inline:
                return $this->evalInlineDefinition($definition, $callbackType);
        }
        throw new InvalidSchemaException(
            [
                'unknown' => "Could not process guard definition",
            ]
        );
    }

    /**
     * @throws InvalidSchemaException
     */
    private function evalInlineDefinition(array $definition, CallbackType $callbackType): callable
    {
        if (($callbackType === CallbackType::Guard || $callbackType == CallbackType::Action) && !isset($definition['trigger'])) {
            throw new InvalidSchemaException(
                [
                    'unknown' => "Inline Guards and Actions must define a 'trigger' FQCN",
                ]
            );
        }
        $functionBody = $definition['callback'];
        match ($callbackType) {
            CallbackType::onEntry, CallbackType::onExit => $signature = '$state, $from, $machine',
            CallbackType::Action => $signature = $definition['trigger'] . ' $trigger, $state, $machine',
            CallbackType::Guard => $signature = $definition['trigger'] . ' $trigger, $transition, $machine'
        };
        $code = '
        return function (' . $signature . ') {
            ' . $functionBody . '
        };
    ';

        return eval($code);
    }

    private function isService(string $defined): bool
    {
        return str_starts_with($defined, '@');
    }
}
