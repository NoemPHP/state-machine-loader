<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Nette\Schema\Elements\Type;
use Nette\Schema\Expect;
use Nette\Schema\Message;
use Nette\Schema\Processor;
use Nette\Schema\ValidationException;
use Noem\State\Context\ContextProviderInterface;
use Noem\State\InMemoryStateStorage;
use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\NestedStateInterface;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\StateInterface;
use Noem\State\StateMachine;
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

    private ContainerInterface $serviceLocator;

    private ContextProcessor $contextProcessor;

    public function __construct(
        private array $stateGraph,
        ?ContainerInterface $serviceLocator = null
    ) {
        $this->serviceLocator = $serviceLocator ?? new FallbackContainer();
    }

    public function createMachine(string $initialState = null): StateMachine
    {
        $definitions = $this->definitions(); // A flat list of all defined states
        $initialState = $initialState
            ? $definitions->get($initialState)
            : $definitions->initial();
        $stateMachine = new StateMachine(
            $this->transitions(), // Our generated TransitionProvider
            new InMemoryStateStorage($initialState),
            null,
            $this->context()
        );
        $stateMachine->attach($this->observer());

        return $stateMachine;
    }

    /**
     * @throws InvalidSchemaException
     */
    public function transitions(): TransitionProviderInterface
    {
        return $this->transitionProcessor->create($this->definitions(), $this->serviceLocator);
    }

    /**
     * @throws InvalidSchemaException
     */
    public function context(): ContextProviderInterface
    {
        return $this->contextProcessor->create($this->definitions(), $this->serviceLocator);
    }

    /**
     * @throws InvalidSchemaException
     */
    public function definitions(): StateDefinitions
    {
        if (!$this->definitions) {
            $this->assertValidGraph();
            $this->eventProcessor = new EventProcessor();
            $this->transitionProcessor = new TransitionProcessor();
            $this->contextProcessor = new ContextProcessor();

            $this->processDefinitions($this->stateGraph);
            $this->definitions = new StateDefinitions($this->stateMap);
        }

        return $this->definitions;
    }

    /**
     * @throws InvalidSchemaException
     */
    private function assertValidGraph()
    {
        $callbackSchema = Expect::anyOf(Expect::string(), Expect::type('callable'));

        $transitionSchema = Expect::anyOf(
            Expect::string(),
            Expect::structure([
                'target' => Expect::string()->required(),
                'guard' => $callbackSchema,
            ])
        );
        $contextSchema = Expect::anyOf(
            Expect::string(),
            Expect::array()
        );
        // We cannot inline $stateSchema in its children, so we initialize it later
        $nestedStateSchema = new Type('array');
        $stateSchema = Expect::structure([
            'label' => Expect::string(),
            'parallel' => Expect::bool(),
            'initial' => Expect::string(),
            'transitions' => Expect::listOf($transitionSchema),
            'children' => $nestedStateSchema,
            'onEntry' => $callbackSchema,
            'onExit' => $callbackSchema,
            'action' => $callbackSchema,
            'context' => $contextSchema,
        ]);
        $nestedStateSchema->items($stateSchema);
        $schema = Expect::arrayOf($stateSchema);
        $processor = new Processor();
        try {
            $processor->process($schema, $this->stateGraph);
        } catch (ValidationException $e) {
            throw new InvalidSchemaException(array_map(fn(Message $m) => $m->toString(), $e->getMessageObjects()));
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

    /**
     * @throws InvalidSchemaException
     */
    private function processDefinition(string $name, array $definition, array &$out = []): void
    {
        if (isset($this->stateMap[$name])) {
            throw new InvalidSchemaException([$name => 'Duplicate state name detected']);
        }
        $state = $this->createStateInstance($name, $definition);
        $this->stateMap[$name] = $state;
        $out[$name] = $state;

        $this->eventProcessor->process($name, $definition, $this->nestingLevel);
        $this->transitionProcessor->process($name, $definition, $this->nestingLevel);
        $this->contextProcessor->process($name, $definition, $this->nestingLevel);
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
            if ($child instanceof NestedStateInterface) {
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
            if ($child instanceof NestedStateInterface) {
                $child->setParent($state);
            }
        }
        if ($initial && isset($this->stateMap[$initial])) {
            $state->setInitial($this->stateMap[$initial]);
        }

        return $state;
    }

    /**
     * @throws InvalidSchemaException
     */
    public function observer(): StateMachineObserver
    {
        return $this->eventProcessor->create($this->definitions(), $this->serviceLocator);
    }
}
