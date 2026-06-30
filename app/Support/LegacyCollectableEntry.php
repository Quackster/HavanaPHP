<?php

namespace App\Support;

class LegacyCollectableEntry
{
    public function __construct(
        private readonly string $sprite,
        private readonly string $name,
        private readonly string $description,
    ) {}

    public function getSprite(): string
    {
        return $this->sprite;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
