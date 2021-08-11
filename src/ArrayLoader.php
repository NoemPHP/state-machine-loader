<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\StateInterface;
use Noem\State\Transition\TransitionProviderInterface;
use Psr\Container\ContainerInterface;

class ArrayLoader implements LoaderInterface
{

    /**
     * @var StateInterface[]
     */
    private array $stateMap = [];

    private int $nestingLevel = 0;

    private TransitionProcessor $transitionProcessor;

    private EventProcessor $eventProcessor;

    private ?StateDefinitions $definitions = null;

    public function __construct(
        private array $stateGraph,
        private ?ContainerInterface $serviceLocator = null
    ) {
    }

    /**
     * @throws InvalidSchemaException
     */
    public function definitions(): StateDefinitions
    {
        if (!$this->definitions) {
            $validator = new Validator();
            $validator->validate(
                $this->stateGraph,
                (object) ['$ref' => 'file://'.realpath(__DIR__.'/schema.json')],
                Constraint::CHECK_MODE_TYPE_CAST
            );
            if (!$validator->isValid()) {
                throw new InvalidSchemaException($validator->getErrors());
            }
            $this->eventProcessor = new EventProcessor();
            $this->transitionProcessor = new TransitionProcessor();

            $this->processDefinitions($this->stateGraph);
            $this->definitions = new StateDefinitions($this->stateMap);
        }

        return $this->definitions;
    }

    private function processDefinition(string $name, array $definition, array &$out = []): void
    {
        $state = $this->createStateInstance($name, $definition);
        $this->stateMap[$name] = $state;
        $out[$name] = $state;

        $this->eventProcessor->process($name, $definition, $this->nestingLevel);
        $this->transitionProcessor->process($name, $definition, $this->nestingLevel);
    }

    private function createStateInstance(string $name, array $definition): StateInterface
    {
        return match (true) {
            $this->isParallelStateDefinition($definition)
            => $this->createParallel($name, $definition),
            $this->isHierarchicalStateDefinition($definition)
            => $this->createHierarchical($name, $definition),
            default
            => new SimpleState($name),
        };
    }

    private function isParallelStateDefinition(array $definition): bool
    {
        return isset($definition['parallel'])
            && $definition['parallel'] === true;
    }

    private function createParallel(string $name, array $definition): StateInterface
    {
        $childDefinitions = $definition['children'] ?? [];
        $children = [];
        $this->processDefinitions($childDefinitions, $children);
        $state = new ParallelState($name, null, ...$children);
        foreach ($children as $child) {
            if ($child instanceof HierarchicalState) {
                $child->setParent($state);
            }
        }

        return $state;
    }

    private function isHierarchicalStateDefinition(array $definition): bool
    {
        return isset($definition['children'])
            || $this->nestingLevel > 1;
    }

    private function createHierarchical(string $name, array $definition): StateInterface
    {
        $initial = $definition['initial'] ?? null;
        $childDefinitions = $definition['children'] ?? [];
        $children = [];
        $this->processDefinitions($childDefinitions, $children);

        $state = new HierarchicalState($name, null, ...$children);
        foreach ($children as $child) {
            if ($child instanceof HierarchicalState) {
                $child->setParent($state);
            }
        }
        if ($initial && isset($this->stateMap[$initial])) {
            $state->setInitial($this->stateMap[$initial]);
        }

        return $state;
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
        return $this->transitionProcessor->create($this->definitions(), $this->serviceLocator);
    }

    public function observer(): StateMachineObserver
    {
        return $this->eventProcessor->create($this->definitions(), $this->serviceLocator);
    }
}