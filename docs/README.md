# State Machine Loader
[![Testing](https://github.com/NoemPHP/state-machine-loader/actions/workflows/testing.yml/badge.svg)](https://github.com/NoemPHP/state-machine-loader/actions/workflows/testing.yml)

Creates [State Machine](https://noemphp.github.io/state-machine/) instances from various sources.

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
|transitions|array<[Transition](#transition)> | - | `["target-state"]`| Define which states can be reached from this state |
|children|`object`| - |`{"subState": {}}`| `Dictionary<string,State>`. Recursion |
|parallel|`boolean`| - |`true`| Flag this state as parallel.<br>All of its children will be active at the same time  |
|initial|`string`| - | `"subState"` | Only used for hierarchical states.<br>Determines which child state is initially active.<br> Defaults to the first child if omitted|
|onEntry|[Callback](#callback)  | - | `"my_php_function"`<br>`"@myContainerEntry"` | An action to run when this state is entered. |
|onExit|[Callback](#callback)  | - | `"my_php_function"`<br>`"@myContainerEntry"` | An action to run when this state is exited.  |



### Transition

As an alternative shorthand, you can just define a `string` with the target state. 
This will result in a simple transition that is not enabled by any event or guard and thus will be immediately enabled as soon as the state machine is triggered by any event.
This can be useful for chaining transitions, eg. when you are more interested in the series of enEntry/onExit events than the intermediate states.
The full definition of a transition is an `object` though:

|Key|Type|Required|Example|Comment  |
|---|---|---|---|---|
|target|string| * | `my-state` |   |
|guard|string| - |`"MyEventClassName"`|   |


### Callback

This is currently just a `string` which is checked for `is_callable()` (->allowing you to pass the names of PHP functions or static methods).
However, it is also possible to pull callbacks from a container:

You can optionally pass a PSR-11 `ContainerInterface` into the loader object. It will be used whenever a callback is prefixed with `"@"`.
For example, if you define `onEntry: "@onEnterFoo"`, this will result in `$callback = $container->get('onEnterFoo')`.
You can use this to integrate your framework's DI container into the FSM's event handling.

## Full example

```yaml

off:
  transitions:
    -

```
