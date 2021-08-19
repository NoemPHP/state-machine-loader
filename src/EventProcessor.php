<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Noem\State\EventManager;
use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\State\StateDefinitions;
use Noem\State\Util\ParameterDeriver;
use Psr\Container\ContainerInterface;

/**
 * @extends ProcessorInterface<EventManager>
 */
class EventProcessor implements ProcessorInterface
{
    use ServiceResolverTrait;

    private array $events = [];

    public function process(string $name, array $data, int $depth): void
    {
        foreach (['onEntry', 'onExit', 'action'] as $key) {
            if (isset($data[$key])) {
                $this->events[$key][] = [$name, $data[$key]];
            }
        }
    }

    /**
     * @throws InvalidSchemaException
     */
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
                $this->assertValidAction(
                    $this->resolveService($h[1], $serviceLocator),
                    $h,
                )
            )
        );

        return $em;
    }

    private function assertValidAction(callable $handler, array $rawDefinition): callable
    {
        try {
            $parameter = ParameterDeriver::getParameterType($handler);
        } catch (\InvalidArgumentException $e) {
            [$state, $serviceName] = $rawDefinition;
            throw new InvalidSchemaException([$state => "Invalid event handler '{$serviceName}'"], $e);
        }

        return $handler;
    }
}
