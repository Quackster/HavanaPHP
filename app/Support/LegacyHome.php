<?php

namespace App\Support;

class LegacyHome
{
    public function __construct(private readonly string $background) {}

    public function getBackground(): string
    {
        return $this->background;
    }
}
