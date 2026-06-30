<?php

namespace App\Support;

class HousekeepingGroupView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function name(): string
    {
        return (string) $this->row->name;
    }

    public function description(): string
    {
        return (string) $this->row->description;
    }

    public function ownerId(): int
    {
        return (int) $this->row->owner_id;
    }

    public function ownerName(): string
    {
        return (string) ($this->row->owner_name ?? '');
    }

    public function roomId(): int
    {
        return (int) $this->row->room_id;
    }

    public function badge(): string
    {
        return (string) $this->row->badge;
    }

    public function recommended(): bool
    {
        return (bool) $this->row->recommended;
    }

    public function background(): string
    {
        return (string) $this->row->background;
    }

    public function views(): int
    {
        return (int) $this->row->views;
    }

    public function topics(): int
    {
        return (int) $this->row->topics;
    }

    public function groupType(): int
    {
        return (int) $this->row->group_type;
    }

    public function forumType(): int
    {
        return (int) $this->row->forum_type;
    }

    public function forumPermission(): int
    {
        return (int) $this->row->forum_premission;
    }

    public function alias(): string
    {
        return (string) ($this->row->alias ?? '');
    }

    public function createdAt(): string
    {
        return (string) $this->row->created_at;
    }

    public function memberCount(): int
    {
        return (int) ($this->row->member_count ?? 0);
    }

    public function pendingCount(): int
    {
        return (int) ($this->row->pending_count ?? 0);
    }

    public function threadCount(): int
    {
        return (int) ($this->row->thread_count ?? 0);
    }

    public function staffPick(): bool
    {
        return (bool) ($this->row->staff_pick ?? false);
    }
}
