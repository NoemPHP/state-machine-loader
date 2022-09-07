<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\Context\ContextProviderInterface;
use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\Loader\ProcessorInterface;
use Noem\State\State\StateDefinitions;
use Psr\Container\ContainerInterface;

/**
 * @extends ProcessorInterface<ContextProviderInterface>
 */
class ContextProcessor implements ProcessorInterface
{
    private array $scalarData = [];

    private array $serviceAliases = [];

    public function process(string $name, array $data, int $depth): void
    {
        if (!isset($data['context'])) {
            return;
        }
        $context = $data['context'];
        if (is_array($context)) {
            $this->scalarData[$name] = $context;

            return;
        }
        if (is_string($context) && str_starts_with($context, '@')) {
            $this->serviceAliases[$name] = substr($context, 1);
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function create(StateDefinitions $stateDefinitions, ContainerInterface $serviceLocator): mixed
    {
        foreach ($this->serviceAliases as $state => $serviceAlias) {
            if (!$serviceLocator->has($serviceAlias)) {
                throw new InvalidSchemaException(
                    [$state => "Context service id '{$serviceAlias}' not found in container"]
                );
            }
        }

        return new ContainerAwareContextProvider(
            new ContainerLayer(
                $this->scalarData,
                new AliasingContainer($this->serviceAliases, $serviceLocator)
            )
        );
    }
}
