# State Machine Loader

[![CI](https://github.com/NoemPHP/state-machine-loader/actions/workflows/ci.yml/badge.svg)](https://github.com/NoemPHP/state-machine-loader/actions/workflows/ci.yml)

Creates [State Machine](https://noemphp.github.io/state-machine/) instances from various sources.

## Installation

Install this package via composer:

`composer require noem/state-machine-loader`

## Schema

All input data is validated against a JSON schema
using [justinrainbow/json-schema](https://github.com/justinrainbow/json-schema). The raw schema file can be found
at [src/schema.json](https://github.com/NoemPHP/state-machine-loader/blob/master/src/schema.json)
Below is a description of all the relevant entities:

### State

| Key         | Type                             | Required | Example                                        | Comment                                                                                                                            |
|-------------|----------------------------------|---------|------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------|
| transitions | array<[Transition](#transition)> | -       | `["target-state"]`                             | Define which states can be reached from this state                                                                                 |
| children    | `object`                         | -       | `{"subState": {}}`                             | `Dictionary<string,State>`. Recursion                                                                                              |
| parallel    | `boolean`                        | -       | `true`                                         | Flag this state as parallel.<br>All of its children will be active at the same time                                                |
| initial     | `string`                         | -       | `"subState"`                                   | Only used for hierarchical states.<br>Determines which child state is initially active.<br> Defaults to the first child if omitted |
| onEntry     | [Callback](#callback)            | -       | `"my_php_function"`<br>`"@myContainerEntry"`   | An action to run when this state is entered.                                                                                       |
| onExit      | [Callback](#callback)            | -       | `"my_php_function"`<br>`"@myContainerEntry"`   | An action to run when this state is exited.                                                                                        |
| context     | `string`,`object`                | - | `{"hello": "world"}}`<br>`"@myContainerEntry"` | Initial context data.                                                                                                              |

### Transition

As an alternative shorthand, you can just define a `string` with the target state. This will result in a simple
transition that is not enabled by any event or guard and thus will be immediately enabled as soon as the state machine
is triggered by any event. This can be useful for chaining transitions, eg. when you are more interested in the series
of enEntry/onExit events than the intermediate states. The full definition of a transition is an `object` though:

|Key| Type             |Required|Example|Comment  |
|---|------------------|---|---|---|
|target| string           | * | `my-state` |   |
|guard| `string`,`array` | - |`"MyEventClassName"`|   |

### Callback

Event handlers (guards, actions, entry/exit handlers) are defined with a flexible syntax

#### Shorthand method
In its simplest form, this is just a `string` which is checked for `is_callable()` (->allowing you to pass the names of PHP
functions or static methods). 

```yaml
my_state:
    action: var_dump
```

However, it is also possible to pull callbacks from a container:

You can optionally pass a PSR-11 `ContainerInterface` into the loader object. It will be used whenever a callback is
prefixed with `"@"`. For example, if you define `onEntry: "@onEnterFoo"`, this will result
in `$callback = $container->get('onEnterFoo')`. You can use this to integrate your framework's DI container into the
FSM's event handling.

```yaml
my_state:
    action: @onPostRequest
```

#### Extended syntax

Shorthands are great for prototyping and/or very *functional* codebases, 
but they make code reuse hard due to the lack of parametrization mechanisms. For more flexibility, you may opt to define
your handlers as objects.

##### Factory

The factory allows you to configure a "function that returns a function" with the arguments that you define next to it.

```yaml
my_state:
    action:
        type: factory
        factory: createMyStateAction
        arguments:
            - Hello world
            - false
```

This assumes a function like this in your code:

```php

function createMyStateAction(string $greeting, bool $isError){

    return function(object $input) use ($greeting, $echo){
        $input->greeting = $greeting;
        $input->isError
        return $input;
    }

}

```
This pattern allows you to reuse the same handlers and customize their behaviour from YAML. This can be especially useful
for writing guards: Say you want to ensure a state has been active for at least 5 seconds. Without factories, 
you are more or less forced to create some version of a `hasBeenInStateForFiveSeconds` function. 
If you want to use the same logic to wait for 10 seconds elsewhere in your application, you're in a tough situation now.
With a factory definition, you can instead write a `createTimeoutGuard` which can return guards for any interval you want.

#### Parameters
 

## Full example

All event handlers are assumed to be configured in a Service Container.

<!-- EXAMPLE -->

```yaml
off:
    transitions:
        - on # Shorthand used

on:
    parallel: true
    context:
        hello: "world"
    onEntry: '@onBooted'
    children:
        foo:
            action: '@sayMyName'

        bar:
            action: '@sayMyName'
            parallel: true
            children:
                bar_1:
                    action: '@sayMyName'
                    children:
                        bar_1_1:
                            transitions:
                            - target: 'bar_1_2'
                              guard: '@guardBar_1_2' 
                        bar_1_2:
                            transitions:
                            - target: 'bar_1_1'
                              guard: 
                                  type: factory
                                  # Executes a function that creates the actual guard based on the given parameters
                                  factory: '@myGuardFactory'
                                  arguments:
                                      - 'hello'
                                      - 'world'
                bar_2:
                    action: '@sayMyName'

        baz:
            initial: 'substate2' # if not specified, it would use the first child, 'substate1'
            action: 
              - '@sayMyName'
              - type: factory
                factory: '@myActionFactory'
                arguments:
                    - 'lorem'
                    - '%getIpsum%'
            children:
                substate1:
                    action: '@sayMyName'

                substate2:
                    action: '@sayMyName'
                    transitions:
                        -   target: 'substate3'
                            guard: 
                            # Multiple guards for one transition are possible. Any of them can allow the transition
                              - '@someOtherGuard'
                              - '@guardSubstate3'
                substate3:
                    action: '@sayMyName'

    transitions:
        # If an exception is used as a trigger,
        # it can be used to perform a graceful shutdown
        -   target: error
            guard: Throwable

error:
    onEntry: 
      - '@onException'
      - '@anotherErrorHandler'
    context: '@helloWorldService'
    transitions:
        - off
```

<!-- EXAMPLE -->
