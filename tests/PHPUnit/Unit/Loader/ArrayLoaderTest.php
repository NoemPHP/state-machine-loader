<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Loader;

use Noem\State\Loader\ArrayLoader;
use Noem\State\Loader\Exception\InvalidSchemaException;
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
            $this->expectException(InvalidSchemaException::class);
        }
        $serviceLocator = \Mockery::mock(ContainerInterface::class);
        $serviceLocator->allows('has');
        $serviceLocator->shouldReceive('get')->andReturnUsing(
            function ($id) use ($services) {
                return $services[$id];
            }
        );
        $sut = new ArrayLoader($inputData, $serviceLocator);

        $validator($sut->definitions(), $sut->transitions(), $sut->observer());
    }
}
