<?php

namespace App\Support;

use Countable;

class LegacyMap implements Countable
{
    /** @param array<int|string, mixed> $items */
    public function __construct(private readonly array $items) {}

    /** @return list<LegacyMapEntry> */
    public function entrySet(): array
    {
        $entries = [];

        foreach ($this->items as $key => $value) {
            $entries[] = new LegacyMapEntry($key, $value);
        }

        return $entries;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
