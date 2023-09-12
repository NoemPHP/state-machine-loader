<?php

declare(strict_types=1);

namespace Noem\State\Loader;

enum CallbackType: string
{

    case Action = 'action';
    case onEntry = 'onEntry';
    case onExit = 'onExit';
    case Guard = 'guard';
}
