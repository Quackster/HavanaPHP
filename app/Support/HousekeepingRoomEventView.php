<?php

namespace App\Support;

class HousekeepingRoomEventView
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

    public function categoryId(): int
    {
        return (int) $this->row->category_id;
    }

    public function name(): string
    {
        return (string) $this->row->name;
    }

    public function description(): string
    {
        return (string) $this->row->description;
    }

    public function expireTime(): int
    {
        return (int) $this->row->expire_time;
    }

    public function tags(): string
    {
        return (string) ($this->row->tags ?? '');
    }
}
