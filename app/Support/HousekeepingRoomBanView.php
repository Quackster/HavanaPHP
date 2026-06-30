<?php

namespace App\Support;

class HousekeepingRoomBanView
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

    public function expireAt(): int
    {
        return (int) $this->row->expire_at;
    }
}
