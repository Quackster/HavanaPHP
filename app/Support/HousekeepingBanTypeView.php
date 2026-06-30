<?php

namespace App\Support;

class HousekeepingBanTypeView
{
    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }
}
