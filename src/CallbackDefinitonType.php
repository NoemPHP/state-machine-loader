<?php

namespace Noem\State\Loader;

enum CallbackDefinitonType: string
{

    case Factory = 'factory';
    case Inline = 'inline';
}
