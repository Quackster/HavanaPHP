<?php

namespace App\Support;

class HousekeepingRoomView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->value('id');
    }

    public function ownerId(): int
    {
        return (int) $this->value('owner_id');
    }

    public function ownerName(): string
    {
        return (string) $this->value('username');
    }

    public function categoryId(): int
    {
        return (int) $this->value('category', 2);
    }

    public function name(): string
    {
        return (string) $this->value('name');
    }

    public function description(): string
    {
        return (string) $this->value('description');
    }

    public function model(): string
    {
        return (string) $this->value('model');
    }

    public function ccts(): string
    {
        return (string) $this->value('ccts');
    }

    public function wallpaper(): int
    {
        return (int) $this->value('wallpaper');
    }

    public function floor(): int
    {
        return (int) $this->value('floor');
    }

    public function landscape(): string
    {
        return (string) $this->value('landscape', '0');
    }

    public function showOwnerName(): bool
    {
        return (bool) $this->value('showname', true);
    }

    public function superUsers(): bool
    {
        return (bool) $this->value('superusers');
    }

    public function accessType(): int
    {
        return (int) $this->value('accesstype');
    }

    public function password(): string
    {
        return (string) $this->value('password');
    }

    public function visitorsNow(): int
    {
        return (int) $this->value('visitors_now');
    }

    public function visitorsMax(): int
    {
        return (int) $this->value('visitors_max', 25);
    }

    public function rating(): int
    {
        return (int) $this->value('rating');
    }

    public function iconData(): string
    {
        return (string) $this->value('icon_data', '0|0|');
    }

    public function groupId(): int
    {
        return (int) $this->value('group_id');
    }

    public function hidden(): bool
    {
        return (bool) $this->value('is_hidden');
    }

    public function createdAt(): string
    {
        return (string) $this->value('created_at');
    }

    public function updatedAt(): string
    {
        return (string) $this->value('updated_at');
    }

    public function staffPick(): bool
    {
        return (bool) $this->value('staff_pick');
    }

    private function value(string $key, mixed $default = ''): mixed
    {
        return property_exists($this->row, $key) ? $this->row->{$key} : $default;
    }
}
