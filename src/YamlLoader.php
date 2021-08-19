<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Psr\Container\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

class YamlLoader extends ArrayLoader
{

    public function __construct(
        string $yaml,
        ?ContainerInterface $serviceLocator = null
    ) {
        $stateGraph = Yaml::parse($yaml);
        parent::__construct($stateGraph, $serviceLocator);
    }
}
