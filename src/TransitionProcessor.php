<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\State\StateDefinitions;
use Noem\State\Transition\TransitionProvider;
use Noem\State\Transition\TransitionProviderInterface;
use Noem\State\Util\ParameterDeriver;
use Psr\Container\ContainerInterface;

/**
 * @extends ProcessorInterface<TransitionProviderInterface>
 */
class TransitionProcessor implements ProcessorInterface
{
    use ServiceResolverTrait;

    private array $rawTransitions = [];

    public function process(string $name, array $data, int $depth): void
    {
        if (!isset($data['transitions'])) {
            return;
        }
        foreach ($data['transitions'] as $t) {
            if (is_string($t)) {
                $t = ['target' => $t];
            }
            unset($data['source']);
            $t = array_merge(
                [
                    'source' => $name,
                    'guard' => null,
                ],
                $t
            );
            if (is_array($t) && isset($t['target'])) {
                $this->rawTransitions[] = $t;
                continue;
            }
            throw new \InvalidArgumentException('Illegal transition definition');
        }
    }

    /**
     * @param StateDefinitions $stateDefinitions
     * @param ContainerInterface $serviceLocator
     *
     * @return TransitionProviderInterface
     * @throws Exception\InvalidSchemaException
     */
    public function create(
        StateDefinitions $stateDefinitions,
        ContainerInterface $serviceLocator
    ): TransitionProviderInterface {
        $transitionProvider = new TransitionProvider($stateDefinitions);
        foreach ($this->rawTransitions as $rawTransition) {
            $guard = $this->generateGuardParameter($rawTransition['guard'], $serviceLocator);
            $transitionProvider->registerTransition(
                $rawTransition['source'],
                $rawTransition['target'],
                $guard
            );
        }

        return $transitionProvider;
    }

    /**
     * @throws Exception\InvalidSchemaException
     */
    private function generateGuardParameter(
        string|callable|null $definition,
        ContainerInterface $serviceLocator
    ): string|callable|null {
        if (!$definition) {
            return null;
        }
        if (is_callable($definition)) {
            $handle = is_string($definition) ? $definition : get_class($definition);
            return $this->assertValidGuard($definition, $this->generateCallableHandle($handle));
        }

        if ($this->isService($definition)) {
            return $this->assertValidGuard($this->resolveService($definition, $serviceLocator), $definition);
        }

        if (class_exists($definition) || interface_exists($definition)) {
            return $definition;
        }
        throw new InvalidSchemaException();
    }

    /**
     * @throws InvalidSchemaException
     */
    private function assertValidGuard(callable $guard, string $defined): callable
    {
        $returns = ParameterDeriver::getReturnType($guard);
        if ($returns === 'bool') {
            return $guard;
        }
        throw new InvalidSchemaException(
            [
                $defined => "Guards callbacks must return boolean",
            ]
        );
    }

    private function generateCallableHandle(callable $c): string
    {
        return match (true) {
            is_string($c) => $c,
            is_array($c) => get_class($c[0]) . '::' . $c[1],
            default => get_class($c),
        };
    }
}
