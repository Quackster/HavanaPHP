<?php

namespace App\Support;

class LegacyClubGiftItem
{
    public function __construct(
        private readonly string $sprite,
        private readonly string $name,
    ) {}

    public function getSprite(): string
    {
        return $this->sprite;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
