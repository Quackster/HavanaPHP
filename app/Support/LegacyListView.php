<?php

namespace App\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, mixed> */
class LegacyListView implements Countable, IteratorAggregate
{
    /** @param list<mixed> $items */
    public function __construct(private readonly array $items) {}

    public function size(): int
    {
        return count($this->items);
    }

    public function get(int $index): ?string
    {
        return $this->items[$index] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
