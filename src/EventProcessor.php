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

    private array $eventByState = [];

    public function process(string $name, array $data, int $depth): void
    {
        foreach (['onEntry', 'onExit', 'action'] as $key) {
            if (isset($data[$key])) {
                if (!isset($this->eventByState[$name][$key])) {
                    $this->eventByState[$name][$key] = [];
                }
                $this->eventByState[$name][$key] += (array)$data[$key];
            }
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function create(StateDefinitions $stateDefinitions, ContainerInterface $serviceLocator): EventManager
    {
        $em = new EventManager();

        foreach ($this->eventByState as $state => $eventsByType) {
            foreach ($eventsByType as $type => $events) {
                switch ($type) {
                    case 'onEntry':
                        array_walk(
                            $events,
                            fn($e) => $em->addEnterStateHandler(
                                $stateDefinitions->get($state),
                                $this->resolveService($e, $serviceLocator)
                            )
                        );
                        break;
                    case 'onExit':
                        array_walk(
                            $events,
                            fn($e) => $em->addExitStateHandler(
                                $stateDefinitions->get($state),
                                $this->resolveService($e, $serviceLocator)
                            )
                        );
                        break;
                    case 'action':
                        array_walk(
                            $events,
                            fn($e) => $em->addActionHandler(
                                $stateDefinitions->get($state),
                                $this->assertValidAction(
                                    $this->resolveService($e, $serviceLocator),
                                    [$state, $e]
                                )
                            )
                        );
                        break;
                }
            }
        }

        return $em;
    }

    private function assertValidAction(callable $handler, array $rawDefinition): callable
    {
        try {
            $parameter = ParameterDeriver::getParameterType($handler);
        } catch (\InvalidArgumentException $e) {
            [$state, $serviceName] = $rawDefinition;
            $serviceName = is_string($serviceName)
                ? $serviceName
                : get_class($serviceName);
            throw new InvalidSchemaException([$state => "Invalid event handler '{$serviceName}'"], $e);
        }

        return $handler;
    }
}
