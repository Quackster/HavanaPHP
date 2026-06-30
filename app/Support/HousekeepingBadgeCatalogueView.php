<?php

namespace App\Support;

class HousekeepingBadgeCatalogueView
{
    public function __construct(private readonly object $row) {}

    public function badge(): string
    {
        return (string) $this->row->badge;
    }

    public function assignmentCount(): int
    {
        return (int) $this->row->assignment_count;
    }

    public function rankBadge(): bool
    {
        return (bool) $this->row->rank_badge;
    }
}
