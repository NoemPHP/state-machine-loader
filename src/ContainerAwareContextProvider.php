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
    private array $globalContext = [];

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

        return new Context($trigger, array_merge($this->globalContext, $data));
    }

    public function setGlobalContext(array $data): void
    {
        $this->globalContext = $data;
    }
}
