<?php

namespace App\Support;

use Carbon\Carbon;

class LegacyTransaction
{
    public function __construct(
        private readonly mixed $createdAt,
        private readonly int $costCoins,
        private readonly string $description,
    ) {}

    public function getFormattedDate(): string
    {
        return Carbon::parse($this->createdAt)->format('M j, Y');
    }

    public function getCostCoins(): int
    {
        return $this->costCoins;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
