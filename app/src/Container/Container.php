<?php

declare(strict_types=1);

namespace App\Container;

use Closure;

final class Container implements ContainerInterface
{
    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var array<class-string, true> */
    private array $resolving = [];

    /** @param array<class-string, Closure(ContainerInterface): object> $factories */
    public function __construct(private readonly array $factories)
    {
    }

    public function get(string $id): object
    {
        $instance = $this->instances[$id] ?? null;

        if ($instance instanceof $id) {
            return $instance;
        }

        if (!$this->has($id)) {
            throw new ContainerException('Service is not registered: ' . $id);
        }

        if (isset($this->resolving[$id])) {
            throw new ContainerException(
                'Circular dependency: ' . implode(' -> ', [...array_keys($this->resolving), $id]),
            );
        }

        $this->resolving[$id] = true;

        try {
            $instance = ($this->factories[$id])($this);

            if (!$instance instanceof $id) {
                throw new ContainerException('Factory must return an instance of ' . $id);
            }

            $this->instances[$id] = $instance;

            return $instance;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
