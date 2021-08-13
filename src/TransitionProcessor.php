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
        string|null $definition,
        ContainerInterface $serviceLocator
    ): string|callable|null {
        if (!$definition) {
            return null;
        }
        if ($this->isService($definition)) {
            return $this->assertValidGuard($this->resolveService($definition, $serviceLocator), $definition);
        }

        if (class_exists($definition) || interface_exists($definition)) {
            return $definition;
        }
        throw new InvalidSchemaException();
    }

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
}
