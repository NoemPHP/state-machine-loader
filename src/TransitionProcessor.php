<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\State\StateDefinitions;
use Noem\State\Transition\TransitionProvider;
use Noem\State\Transition\TransitionProviderInterface;
use Psr\Container\ContainerInterface;
/**
 * @extends ProcessorInterface<TransitionProviderInterface>
 */
class TransitionProcessor implements ProcessorInterface
{

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
     */
    public function create(StateDefinitions $stateDefinitions, ContainerInterface $serviceLocator): TransitionProviderInterface
    {
        $transitionProvider = new TransitionProvider($stateDefinitions);
        foreach ($this->rawTransitions as $rawTransition) {
            $transitionProvider->registerTransition(
                $rawTransition['source'],
                $rawTransition['target'],
                $rawTransition['guard']
            );
        }

        return $transitionProvider;
    }
}