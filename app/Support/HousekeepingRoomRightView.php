<?php

namespace App\Support;

class HousekeepingRoomRightView
{
    public function __construct(private readonly object $row) {}

    public function userId(): int
    {
        return (int) $this->row->user_id;
    }

    public function username(): string
    {
        return (string) ($this->row->username ?? '');
    }
}
