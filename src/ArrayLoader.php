<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Noem\State\EventManager;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\Transition\TransitionProvider;
use Noem\State\Transition\TransitionProviderInterface;
use Psr\Container\ContainerInterface;

class ArrayLoader implements LoaderInterface
{

    private array $defininions = [];

    private $nestingLevel = 0;

    private array $rawTransitions = [];

    private TransitionProvider $transitionProvider;

    private array $events = [];

    public function __construct(array $stateGraph, private ?ContainerInterface $serviceLocator = null)
    {
        $validator = new Validator();
        $validator->validate(
            $stateGraph,
            (object) ['$ref' => 'file://'.realpath(__DIR__.'/schema.json')],
            Constraint::CHECK_MODE_TYPE_CAST
        );
        if (!$validator->isValid()) {
            throw new \InvalidArgumentException('Invalid Payload.'.json_encode($validator->getErrors()));
        }
        $this->eventManager = new EventManager();
        $this->processDefinitions($stateGraph);
        $this->processTransitions($this->rawTransitions);
    }

    public function definitions(): StateDefinitions
    {
        return new StateDefinitions($this->defininions);
    }

    private function processDefinition(string $name, array $definition, array &$out = []): void
    {
        switch (true) {
            case
                isset($definition['parallel'])
                && $definition['parallel'] === true
            :
                $childDefinitions = $definition['children'] ?? [];
                $children = [];
                $this->processDefinitions($childDefinitions, $children);
                $state = new ParallelState($name, null, ...$children);
                foreach ($children as $child) {
                    if ($child instanceof HierarchicalState) {
                        $child->setParent($state);
                    }
                }
                break;
            case
                isset($definition['children'])
                || $this->nestingLevel > 1
            :
                $childDefinitions = $definition['children'] ?? [];
                $children = [];
                $this->processDefinitions($childDefinitions, $children);

                $state = new HierarchicalState($name, null, ...$children);
                foreach ($children as $child) {
                    if ($child instanceof HierarchicalState) {
                        $child->setParent($state);
                    }
                }
                break;
            default:
                $state = new SimpleState($name);
                break;
        }
        $this->defininions[$name] = $state;
        $out[$name] = $state;

        if (isset($definition['onEntry'])) {
            $handle = $definition['onEntry'];
            if (str_starts_with($handle, '@')) {
                $service = $this->serviceLocator->get(substr($handle, 1));
                $this->events['onEntry'][] = [$state, $service];
            }
        }

        if (isset($definition['onExit'])) {
            $handle = $definition['onExit'];
            if (str_starts_with($handle, '@')) {
                $service = $this->serviceLocator->get(substr($handle, 1));
                $this->events['onExit'][] = [$state, $service];
            }
        }

        if (isset($definition['action'])) {
            $handle = $definition['action'];
            if (str_starts_with($handle, '@')) {
                $service = $this->serviceLocator->get(substr($handle, 1));
                $this->events['action'][] = [$state, $service];
            }
        }

        if (!isset($definition['transitions'])) {
            return;
        }
        foreach ($definition['transitions'] as $t) {
            if (is_string($t)) {
                $t = ['target' => $t];
            }
            unset($definition['source']);
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

    private function processTransitions(array $rawTransitions)
    {
        $this->transitionProvider = new TransitionProvider($this->definitions());
        foreach ($rawTransitions as $rawTransition) {
            $this->transitionProvider->registerTransition(
                $rawTransition['source'],
                $rawTransition['target'],
                $rawTransition['guard']
            );
        }
    }

    private function processDefinitions(array $definitions, array &$out = []): void
    {
        $this->nestingLevel++;
        foreach ($definitions as $name => $definition) {
            $this->processDefinition($name, $definition, $out);
        }
        $this->nestingLevel--;
    }

    public function transitions(): TransitionProviderInterface
    {
        return $this->transitionProvider;
    }

    public function observer(): StateMachineObserver
    {
        $em = new EventManager();

        isset($this->events['onEntry'])
        and array_walk(
            $this->events['onEntry'],
            fn($h) => $em->addEnterStateHandler($h[0], $h[1])
        );

        isset($this->events['onExit'])
        and array_walk(
            $this->events['onExit'],
            fn($h) => $em->addExitStateHandler($h[0], $h[1])
        );

        isset($this->events['action'])
        and array_walk(
            $this->events['action'],
            fn($h) => $em->addActionHandler($h[0], $h[1])
        );

        return $em;
    }
}