<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\EventManager;
use Noem\State\State\StateDefinitions;
use Psr\Container\ContainerInterface;

/**
 * @extends ProcessorInterface<EventManager>
 */
class EventProcessor implements ProcessorInterface
{

    private array $events = [];

    public function process(string $name, array $data, int $depth): void
    {
        foreach (['onEntry', 'onExit', 'action'] as $key) {
            if (isset($data[$key])) {
                $this->events[$key][] = [$name, $data[$key]];
            }
        }
    }

    public function create(StateDefinitions $stateDefinitions, ContainerInterface $serviceLocator): EventManager
    {
        $em = new EventManager();

        isset($this->events['onEntry'])
        and array_walk(
            $this->events['onEntry'],
            fn($h) => $em->addEnterStateHandler(
                $stateDefinitions->get($h[0]),
                $this->resolveService($h[1], $serviceLocator)
            )
        );

        isset($this->events['onExit'])
        and array_walk(
            $this->events['onExit'],
            fn($h) => $em->addExitStateHandler(
                $stateDefinitions->get($h[0]),
                $this->resolveService($h[1], $serviceLocator)
            )
        );

        isset($this->events['action'])
        and array_walk(
            $this->events['action'],
            fn($h) => $em->addActionHandler(
                $stateDefinitions->get($h[0]),
                $this->resolveService($h[1], $serviceLocator)
            )
        );

        return $em;
    }

    private function resolveService($serviceDefinition, ContainerInterface $serviceLocator): callable
    {
        if (is_callable($serviceDefinition)) {
            return $serviceDefinition;
        }
        if (str_starts_with($serviceDefinition, '@')) {
            return $serviceLocator->get(substr($serviceDefinition, 1));
        }
        //TODO throw
    }
}