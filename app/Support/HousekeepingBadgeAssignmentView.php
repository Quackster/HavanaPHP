<?php

namespace App\Support;

class HousekeepingBadgeAssignmentView
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

    public function badge(): string
    {
        return (string) $this->row->badge;
    }

    public function equipped(): bool
    {
        return (bool) $this->row->equipped;
    }

    public function slotId(): int
    {
        return (int) $this->row->slot_id;
    }
}
