<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Loader;

use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\Loader\YamlLoader;
use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class YamlLoaderTest extends AbstractLoaderTest
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
        $inputData = Yaml::dump($inputData);
        $sut = new YamlLoader($inputData, $serviceLocator);

        $validator($sut->definitions(), $sut->transitions(), $sut->observer());
    }

    public function testCustomYaml()
    {
        $yaml = <<<YAML
foo: 
  children:
    bar: {}
    baz: {}
YAML;
        $sut = new YamlLoader($yaml);
        $def = $sut->definitions();
        $this->assertTrue($def->has('foo'));
        $this->assertTrue($def->has('bar'));
        $this->assertTrue($def->has('baz'));
    }
}
