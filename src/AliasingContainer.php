<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Psr\Container\ContainerInterface;

class AliasingContainer implements ContainerInterface
{
    public function __construct(private array $map, private ContainerInterface $inner)
    {
    }

    public function get(string $id)
    {
        if (!isset($this->map[$id])) {
            return $this->inner->get($id);
        }

        return $this->inner->get($this->map[$id]);
    }

    public function has(string $id): bool
    {
        return (isset($this->map[$id]) && $this->inner->has($this->map[$id])) || $this->inner->has($id);
    }
}
