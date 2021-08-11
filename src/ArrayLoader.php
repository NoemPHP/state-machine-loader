<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\Transition\TransitionProviderInterface;
use Psr\Container\ContainerInterface;

class ArrayLoader implements LoaderInterface
{

    private array $stateMap = [];

    private int $nestingLevel = 0;

    private TransitionProcessor $transitionProcessor;

    private EventProcessor $eventProcessor;

    private ?StateDefinitions $definitions = null;

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
        $this->eventProcessor = new EventProcessor();
        $this->transitionProcessor = new TransitionProcessor();

        $this->processDefinitions($stateGraph);
    }

    public function definitions(): StateDefinitions
    {
        if (!$this->definitions) {
            $this->definitions = new StateDefinitions($this->stateMap);
        }

        return $this->definitions;
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
        $this->stateMap[$name] = $state;
        $out[$name] = $state;

        $this->eventProcessor->process($name, $definition, $this->nestingLevel);
        $this->transitionProcessor->process($name, $definition, $this->nestingLevel);
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