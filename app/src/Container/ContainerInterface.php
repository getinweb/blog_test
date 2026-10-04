<?php

declare(strict_types=1);

namespace App\Container;

interface ContainerInterface
{
    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object;

    public function has(string $id): bool;
}
