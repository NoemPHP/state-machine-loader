<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class FallbackContainer implements ContainerInterface
{
    public function get($id)
    {
        throw new class ("Empty fallback Container used") extends \Exception implements NotFoundExceptionInterface {
        };
    }

    public function has($id): bool
    {
        return false;
    }
}
