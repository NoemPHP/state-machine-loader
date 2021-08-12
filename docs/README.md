# State Machine Loader
[![Testing](https://github.com/NoemPHP/state-machine-loader/actions/workflows/testing.yml/badge.svg)](https://github.com/NoemPHP/state-machine-loader/actions/workflows/testing.yml)

Creates [State Machine](https://noemphp.github.io/state-machine/) instanced from various sources.

## Installation
Install this package via composer:

`composer require noem/state-machine-loader`

## Schema

All input data is validated against a JSON schema using [justinrainbow/json-schema](https://github.com/justinrainbow/json-schema).
The raw schema file can be found at [src/schema.json](../src/schema.json)
Below is a description of all the relevant entities:
### State
|Key|Type|Required|Example|Comment  |
|---|---|---|---|---|
|transitions|array/string| - | `["target-state"]`| see [Transition](#transition)|
|children|object| - |`{"subState": {}}`| `Dictionary<string,State>`. Recursion|
|parallel|boolean| - |`true`| Flag this state as parallel. All of its children will be active at the same time|
|initial|string| - | `"subState"` | Only used for hierarchical states. Determines which child state is initially active. Defaults to the first child if omitted|
|onEntry|callback| - | `"my_php_function"` `"@myContainerEntry"` | An action to run when this state is entered. See [Callback](#callback)|
|onExit|callback| - | `"my_php_function"` `"@myContainerEntry"` | An action to run when this state is exited. See [Callback](#callback)|



### Transition

|Key|Type|Required|Example|Comment  |
|---|---|---|---|---|
|target|string| * | `my-state` |   |
|guard|string| - |`"MyEventClassName"`|   |

As an alternative shorthand, you can just define a `string` with the target state

### Callback

Todo

## Service Locator
You can optionally pass a PSR-11 `ContainerInterface` into the loader object. It will be used whenever a callback is prefixed with `"@"`.
Yor example, if you define `onEntry: "@onEnterFoo"`, this will result in `$callback = $container->get('onEnterFoo')`.
You can use this to integrate your framework's DI container into the FSM's event handling.
