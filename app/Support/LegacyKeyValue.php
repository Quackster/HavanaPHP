<?php

namespace App\Support;

class LegacyKeyValue
{
    public function __construct(
        private readonly string $key,
        private readonly int $value,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
