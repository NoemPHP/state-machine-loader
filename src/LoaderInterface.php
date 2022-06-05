<?php

namespace Noem\State\Loader;

use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\StateDefinitions;
use Noem\State\Transition\TransitionProviderInterface;

interface LoaderInterface
{
    public function definitions(): StateDefinitions;

    public function transitions(): TransitionProviderInterface;

    public function observer(): StateMachineObserver;
}
