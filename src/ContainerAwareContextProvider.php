<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\Context\Context;
use Noem\State\Context\ContextProviderInterface;
use Noem\State\ContextInterface;
use Noem\State\StateInterface;
use Psr\Container\ContainerInterface;

class ContainerAwareContextProvider implements ContextProviderInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function createContext(StateInterface $state, object $trigger): ContextInterface
    {
        $data = [];
        if ($this->container->has((string)$state)) {
            $data = $this->container->get((string)$state);
            assert(is_array($data));
        }

        return new Context($trigger, $data);
    }
}
