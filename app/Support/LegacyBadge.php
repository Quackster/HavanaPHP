<?php

namespace App\Support;

class LegacyBadge
{
    public function __construct(private readonly string $badgeCode) {}

    public function getBadgeCode(): string
    {
        return $this->badgeCode;
    }
}
