<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Loader;

use Noem\State\Loader\ArrayLoader;
use Psr\Container\ContainerInterface;

class ArrayLoaderTest extends AbstractLoaderTest
{

    /**
     * @dataProvider loaderData
     */
    public function testLoader(
        array $inputData,
        array $services,
        callable $validator,
        bool $throws = false
    ) {
        if ($throws) {
            $this->expectException(\InvalidArgumentException::class);
        }
        $serviceLocator = \Mockery::mock(ContainerInterface::class);
        $serviceLocator->shouldReceive('get')->andReturnUsing(
            function ($id) use ($services) {
                return $services[$id];
            }
        );
        $sut = new ArrayLoader($inputData, $serviceLocator);

        $validator($sut->definitions(), $sut->transitions());
    }
}