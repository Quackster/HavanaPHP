<?php

namespace App\Support;

class HousekeepingBadgeAuditView
{
    public function __construct(private readonly object $row) {}

    public function action(): string
    {
        return (string) $this->row->action;
    }

    public function staffId(): int
    {
        return (int) $this->row->user_id;
    }

    public function targetId(): int
    {
        return (int) $this->row->target_id;
    }

    public function message(): string
    {
        return (string) $this->row->message;
    }

    public function extraNotes(): string
    {
        return (string) $this->row->extra_notes;
    }

    public function createdAt(): string
    {
        return (string) $this->row->created_at;
    }
}
