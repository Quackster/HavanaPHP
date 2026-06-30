<?php

namespace App\Support;

class HousekeepingGroupMemberView
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

    public function memberRank(): int
    {
        return (int) $this->row->member_rank;
    }

    public function pending(): bool
    {
        return (bool) $this->row->is_pending;
    }

    public function createdAt(): string
    {
        return (string) $this->row->created_at;
    }
}
