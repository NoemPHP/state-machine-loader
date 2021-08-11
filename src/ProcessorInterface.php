<?php

namespace Noem\State\Loader;

use Noem\State\State\StateDefinitions;
use Psr\Container\ContainerInterface;

/**
 * @template T
 */
interface ProcessorInterface
{

    public function process(string $name, array $data, int $depth): void;

    /**
     * @param StateDefinitions $stateDefinitions
     * @param ContainerInterface $serviceLocator
     *
     * @return T
     */
    public function create(StateDefinitions $stateDefinitions, ContainerInterface $serviceLocator): mixed;
}