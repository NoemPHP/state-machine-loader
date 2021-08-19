<?php

declare(strict_types=1);

namespace Noem\State\Loader\Tests;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\InMemoryStateStorage;
use Noem\State\Loader\ArrayLoader;
use Noem\State\StateMachine;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;

abstract class StateMachineTestCase extends LoaderTestCase
{

    private StateMachine $machine;

    /**
     * @param string|array $stateGraph
     * @param string $initialState
     * @param array $serviceConfig
     *
     * @return StateMachine
     * @throws \JsonException
     */
    protected function configureStateMachine(
        string|array $stateGraph,
        string $initialState,
        array $serviceConfig = []

    ): StateMachine {
        $loader = $this->configureLoader($stateGraph, $serviceConfig);
        $tree = $loader->definitions();

        $this->machine = new StateMachine(
            $loader->transitions(),
            new InMemoryStateStorage($tree->get($initialState))
        );
        $this->machine->attach($loader->observer());

        return $this->machine;
    }

    protected function assertIsInState(string $state, ?string $message = null)
    {
        $this->assertThat(
            $this->getStateMachine()->isInState($state),
            Assert::isTrue(),
            $message ?? sprintf('The state machine should currently be in state "%s"', $state)
        );
    }

    protected function getStateMachine(): StateMachine
    {
        return $this->machine;
    }

    protected function assertNotInState(string $state, ?string $message = null)
    {
        $this->assertThat(
            $this->getStateMachine()->isInState($state),
            Assert::logicalNot(Assert::isTrue()),
            $message ?? sprintf('The state machine should currently **NOT** be in state "%s"', $state)
        );
    }
}
