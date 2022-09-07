<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Loader;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\EventManager;
use Noem\State\ObservableStateMachineInterface;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\StateInterface;
use Noem\State\Transition\TransitionInterface;
use Noem\State\Transition\TransitionProviderInterface;

abstract class AbstractLoaderTest extends MockeryTestCase
{

    private array $appState = [];

    public function setUp(): void
    {
        $this->appState = [];
        parent::setUp();
    }

    public function loaderData()
    {
        yield '#1 Non-hierarchical' => [
            [
                'foo' => [
                    'transitions' => [
                        ['target' => 'bar'],
                    ],
                    'onEntry' => '@onEnterFoo',
                    'onExit' => '@onExitFoo',
                ],
                'bar' => [
                    'transitions' => ['baz'],
                ],
                'baz' => ['label' => ''],
            ],
            [
                'onEnterFoo' => function () {
                    $this->appState['onEnterFoo'] = true;
                },
                'onExitFoo' => function () {
                    $this->appState['onExitFoo'] = true;
                },
            ],
            function (
                StateDefinitions $map,
                TransitionProviderInterface $transitions,
                EventManager $observer
            ) {
                $fsm = \Mockery::mock(ObservableStateMachineInterface::class);
                $this->assertTrue($map->has('foo'));
                $this->assertTrue($map->has('bar'));
                $this->assertTrue($map->has('baz'));
                $this->assertInstanceOf(SimpleState::class, $map->get('foo'));
                $this->assertInstanceOf(SimpleState::class, $map->get('bar'));
                $this->assertInstanceOf(SimpleState::class, $map->get('baz'));
                $t = $transitions->getTransitionForTrigger($map->get('foo'), new \stdClass());
                $this->assertInstanceOf(TransitionInterface::class, $t);
                $this->assertSame($map->get('bar'), $t->target());
                $observer->onEnterState($map->get('foo'), $fsm);
                $observer->onExitState($map->get('foo'), $fsm);
                $this->assertTrue(
                    isset($this->appState['onEnterFoo']) && $this->appState['onEnterFoo'],
                    'onEnterFoo handler does not exist!'
                );
            },
            true
        ];
        $array = [
            'foo' => ['label' => ''],
            'bar' => [
                'initial' => 'bar_2',
                'children' => [
                    'bar_1' => ['label' => ''],
                    'bar_2' => ['label' => ''],
                    'bar_3' => ['label' => ''],
                ],
            ],
            'baz' => ['label' => ''],
        ];
        yield '#2 Hierarchical' => [
            $array,
            [],
            function (StateDefinitions $map, TransitionProviderInterface $transitions) use ($array) {
                $this->assertTrue($map->has('foo'));
                $this->assertTrue($map->has('bar'));
                $this->assertTrue($map->has('baz'));
                $this->assertInstanceOf(SimpleState::class, $map->get('foo'));
                $this->assertInstanceOf(SimpleState::class, $map->get('baz'));
                $bar = $map->get('bar');
                assert($bar instanceof HierarchicalState);
                $this->assertInstanceOf(HierarchicalState::class, $bar);
                $children = $bar->children();
                $this->assertCount(3, $children);
                foreach ($bar->children() as $i => $child) {
                    self::assertInstanceOf(StateInterface::class, $child);
                }
                $initial = $bar->initial();
                $this->assertNotNull(
                    $initial,
                    '"bar" should have been configured with an initial state'
                );
                $this->assertSame(
                    $map->get('bar_2'),
                    $bar->initial(),
                    '"bar" should have been configured with an initial state "bar_2"'

                );
            },
        ];
        $array = [
            'foo' => ['label' => ''],
            'bar' => [
                'parallel' => true,
                'children' => [
                    'bar_1' => ['label' => ''],
                    'bar_2' => ['label' => ''],
                    'bar_3' => ['label' => ''],
                ],
            ],
            'baz' => ['label' => ''],
        ];
        yield '#3 Parallel' => [
            $array,
            [],
            function (StateDefinitions $map, TransitionProviderInterface $transitions) use ($array) {
                $this->assertTrue($map->has('foo'));
                $this->assertTrue($map->has('bar'));
                $this->assertTrue($map->has('baz'));
                $this->assertInstanceOf(SimpleState::class, $map->get('foo'));
                $this->assertInstanceOf(SimpleState::class, $map->get('baz'));
                $bar = $map->get('bar');
                assert($bar instanceof ParallelState);
                $this->assertInstanceOf(ParallelState::class, $bar);
                $children = $bar->children();
                $this->assertCount(3, $children);
                foreach ($bar->children() as $i => $child) {
                    self::assertInstanceOf(StateInterface::class, $child);
                }
            },
        ];

        yield '#4 Invalid state config 1' => [
            [
                'foo',
            ],
            [],
            function () {
            },
            true, // Yes, please throw
        ];
        yield '#4 Invalid state config 2' => [
            [
                'foo' => ['label' => ''],
                'bar',
            ],
            [],
            function () {
            },
            true,// Yes, please throw
        ];
        yield '#4 Invalid state config 3' => [
            [
                'bar' => [
                    'children' => [
                        'bar_1' => [],
                        'bar_2',
                    ],
                ],
            ],
            [],
            function () {
            },
            true, // Yes, please throw
        ];
    }
}
