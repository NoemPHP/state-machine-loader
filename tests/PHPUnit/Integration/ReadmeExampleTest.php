<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration;

use Noem\State\InMemoryStateStorage;
use Noem\State\Loader\Exception\InvalidSchemaException;
use Noem\State\Loader\Tests\StateMachineTestCase;
use Noem\State\Loader\YamlLoader;
use Noem\State\StateInterface;
use Noem\State\StateMachine;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReadmeExampleTest extends StateMachineTestCase
{
    private const README_LOCATION = __DIR__ . '/../../../docs/README.md';

    private string $yaml;

    public function setUp(): void
    {
        $readme = file_get_contents(self::README_LOCATION);
        $segments = explode('<!-- EXAMPLE -->', $readme);
        $matches = [];
        preg_match('/`{3}yaml(.*)`{3}/s', $segments[1], $matches);
        $this->yaml = $matches[1];
        parent::setUp();
    }

    /**
     * @throws InvalidSchemaException
     */
    public function testReadmeExample()
    {
        $services = [
            'onBooted' => function () {
            },
            'onException' => function () {
            },
            'anotherErrorHandler' => function () {
            },
            'sayMyName' => function (\stdClass $payload, StateInterface $state) {
                $payload->result[] = (string)$state;
            },
            'guardSubstate3' => fn(\stdClass $trigger): bool => $trigger->moveTo === 'substate3',
            'someOtherGuard' => fn(\stdClass $trigger): bool => false,
            'guardBar_1_1' => fn(\stdClass $trigger): bool => $trigger->moveTo === 'bar_1_1',
            'guardBar_1_2' => fn(\stdClass $trigger): bool => $trigger->moveTo === 'bar_1_2',
            'helloWorldService' => ['hello' => 'world'],
        ];
        $loader = new YamlLoader($this->yaml, $this->createContainer($services));
        $definitions = $loader->definitions();
        $m = new StateMachine(
            $loader->transitions(),
            new InMemoryStateStorage($definitions->get('off')),
            null,
            $loader->context()
        );

        $m->attach($loader->observer());
        $this->assertTrue($m->isInState('off'));

        $m->trigger((object)['hello' => 'world']);

        $this->assertTrue($m->isInState('on'));
        $this->assertTrue($m->isInState('foo'));
        $this->assertTrue($m->isInState('bar'));
        $this->assertTrue($m->isInState('baz'));
        $this->assertTrue($m->isInState('substate2'));

        $payload = (object)['result' => []];
        $m->action($payload);

        $expected = [
            'bar_1',
            'bar_2',
            'substate2',
            'foo',
            'bar',
            'baz',
        ];
        $this->assertSame(
            $expected,
            $payload->result,
            "Actions should have been carried out from the deepest child upwards"
        );

        $m->trigger((object)['moveTo' => '__somewhere']);
        $this->assertNotTrue(
            $m->isInState('substate3'),
            "Transition not enabled by mismatching trigger payload"
        );

        /**
         * Check if the machine can take note of the state change in the nested
         * parallel state
         */
        $m->trigger((object)['moveTo' => 'substate3']);
        $this->assertTrue($m->isInState('substate3'));

        /**
         * Check if the behaviour is still consistent when using a different
         * nested parallel state
         */
        $this->assertTrue($m->isInState('bar_1_1'));
        $m->trigger((object)['moveTo' => 'bar_1_2']);
        $this->assertTrue($m->isInState('bar_1_2'));

        /**
         * Moving back should also work, right?
         */
        $m->trigger((object)['moveTo' => 'bar_1_1']);
        $this->assertTrue($m->isInState('bar_1_1'));

        $m->trigger(new \Exception("some_error"));
        $this->assertTrue($m->isInState('error'));

        foreach (['on','error'] as $contextStateName) {
            $state = $definitions->get($contextStateName);
            $context = $m->context($state);
            $this->assertTrue(isset($context['hello']));
            $this->assertSame($context['hello'], 'world');
        }
    }

    private function createContainer(array $services): ContainerInterface
    {
        return new class ($services) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get($id)
            {
                if (!$this->has($id)) {
                    throw new class ("Service '{$id}' not found") extends \Exception implements
                        NotFoundExceptionInterface
                    {
                    };
                }

                return $this->services[$id];
            }

            public function has($id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }
}
