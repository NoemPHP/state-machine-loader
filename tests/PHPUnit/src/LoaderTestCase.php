<?php

declare(strict_types=1);

namespace Noem\State\Loader\Tests;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\InMemoryStateStorage;
use Noem\State\Loader\ArrayLoader;
use Noem\State\Loader\LoaderInterface;
use Noem\State\StateMachine;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

abstract class LoaderTestCase extends MockeryTestCase
{

    private StateMachine $machine;

    /**
     * @param array $stateGraph
     * @param string $initialState
     * @param array $serviceConfig
     */
    protected function configureStateMachine(
        array $stateGraph,
        string $initialState,
        array $serviceConfig = []

    ): StateMachine {
        $serviceLocator = \Mockery::mock(ContainerInterface::class);
        $serviceLocator->shouldReceive('get')->andReturnUsing(
            function ($id) use ($serviceConfig) {
                return $serviceConfig[$id];
            }
        );
        $loader = new ArrayLoader($stateGraph, $serviceLocator);
        $tree = $loader->definitions();

        $this->machine = new StateMachine(
            $loader->transitions(),
            new InMemoryStateStorage($tree->get($initialState))
        );
        $this->machine->attach($loader->observer());

        return $this->machine;
    }

    /**
     * @throws \JsonException
     */
    protected function configureLoader(string|array $data, array $serviceConfig = []): LoaderInterface
    {
        $serviceLocator = \Mockery::mock(ContainerInterface::class);
        $serviceLocator->shouldReceive('get')->andReturnUsing(
            function ($id) use ($serviceConfig) {
                return $serviceConfig[$id];
            }
        );
        if (is_array($data)) {
            return new ArrayLoader($data, $serviceLocator);
        }
        try {
            $data = Yaml::parse($data);

            return new ArrayLoader($data, $serviceLocator);
        } catch (ParseException $e) {
            $data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

            return new ArrayLoader($data, $serviceLocator);
        }
    }
}
