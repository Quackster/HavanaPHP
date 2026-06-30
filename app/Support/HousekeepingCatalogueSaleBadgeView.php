<?php

namespace App\Support;

class HousekeepingCatalogueSaleBadgeView
{
    public function __construct(private readonly object $row) {}

    public function saleCode(): string
    {
        return (string) $this->row->sale_code;
    }

    public function badgeCode(): string
    {
        return (string) $this->row->badge_code;
    }
}
