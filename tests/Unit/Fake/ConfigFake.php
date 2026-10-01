<?php

declare(strict_types=1);

namespace hkyss\Tune\Tests\Unit\Fake;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;

final class ConfigFake implements Repository
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private array $items = [],
    ) {
    }

    public function has(mixed $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * @param array<mixed>|string $key
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param array<mixed>|string $key
     */
    public function set(mixed $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $name => $item) {
            Arr::set($this->items, $name, $item);
        }
    }

    public function prepend(mixed $key, mixed $value): void
    {
        $this->set($key, array_merge([$value], (array) $this->get($key, [])));
    }

    public function push(mixed $key, mixed $value): void
    {
        $this->set($key, array_merge((array) $this->get($key, []), [$value]));
    }
}
