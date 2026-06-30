<?php

namespace App\Support;

class LegacyNewsCategory
{
    public function __construct(
        private readonly int $id,
        private readonly string $label,
        private readonly string $index,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIndex(): string
    {
        return $this->index;
    }
}
