<?php

declare(strict_types=1);

namespace Noem\State\Loader\Exception;

use Noem\State\Exception\StateMachineExceptionInterface;

abstract class LoaderException extends \Exception implements StateMachineExceptionInterface
{


}
