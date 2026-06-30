<?php

namespace App\Support;

class LegacyMapEntry
{
    public function __construct(
        private readonly int|string $key,
        private readonly mixed $value,
    ) {}

    public function getKey(): int|string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
