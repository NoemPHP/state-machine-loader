<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Loader;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use Noem\State\State\HierarchicalState;
use Noem\State\State\ParallelState;
use Noem\State\State\SimpleState;
use Noem\State\State\StateDefinitions;
use Noem\State\StateInterface;
use Noem\State\Transition\TransitionInterface;
use Noem\State\Transition\TransitionProviderInterface;

abstract class AbstractLoaderTest extends MockeryTestCase
{

    public function loaderData()
    {
        yield '#1 Non-hierarchical' => [
            [
                'foo' => [
                    'transitions' => [
                        ['target' => 'bar'],
                    ],
                    'onEntry' => '@onEnterFoo',
                ],
                'bar' => [
                    'transitions' => ['baz'],
                ],
                'baz' => ['label' => ''],
            ],
            [
                'onEnterFoo' => function () {
                },
            ],
            function (StateDefinitions $map, TransitionProviderInterface $transitions) {
                $this->assertTrue($map->has('foo'));
                $this->assertTrue($map->has('bar'));
                $this->assertTrue($map->has('baz'));
                $this->assertInstanceOf(SimpleState::class, $map->get('foo'));
                $this->assertInstanceOf(SimpleState::class, $map->get('bar'));
                $this->assertInstanceOf(SimpleState::class, $map->get('baz'));
                $t = $transitions->getTransitionForTrigger($map->get('foo'), new \stdClass());
                $this->assertInstanceOf(TransitionInterface::class, $t);
                $this->assertSame($map->get('bar'), $t->target());
            },
        ];
        $array = [
            'foo' => ['label' => ''],
            'bar' => [
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
            true,
        ];
        yield '#4 Invalid state config 2' => [
            [
                'foo' => ['label' => ''],
                'bar',
            ],
            [],
            function () {
            },
            true,
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
            true,
        ];
    }
}