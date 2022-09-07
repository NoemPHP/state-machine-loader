<?php

declare(strict_types=1);

namespace Noem\State\Loader;

use Psr\Container\ContainerInterface;

class ContainerLayer implements ContainerInterface
{
    public function __construct(private array $data, private ContainerInterface $inner)
    {
    }

    public function get(string $id)
    {
        return $this->data[$id] ?? $this->inner->get($id);
    }

    public function has(string $id): bool
    {
        return isset($this->data[$id]) || $this->inner->has($id);
    }
}
