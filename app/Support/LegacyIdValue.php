<?php

namespace App\Support;

class LegacyIdValue
{
    public function __construct(private readonly int $id) {}

    public function getId(): int
    {
        return $this->id;
    }
}
