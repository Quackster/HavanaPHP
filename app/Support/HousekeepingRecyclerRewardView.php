<?php

namespace App\Support;

class HousekeepingRecyclerRewardView
{
    public function __construct(private readonly object $row) {}

    public function sprite(): string
    {
        return (string) $this->row->sprite;
    }

    public function orderId(): int
    {
        return (int) $this->row->order_id;
    }

    public function chance(): int
    {
        return (int) $this->row->chance;
    }
}
