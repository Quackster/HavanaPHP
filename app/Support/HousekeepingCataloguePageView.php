<?php

namespace App\Support;

class HousekeepingCataloguePageView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function oldId(): int
    {
        return (int) $this->row->old_id;
    }

    public function parentId(): int
    {
        return (int) $this->row->parent_id;
    }

    public function orderId(): int
    {
        return (int) $this->row->order_id;
    }

    public function minRole(): int
    {
        return (int) $this->row->min_role;
    }

    public function navigatable(): bool
    {
        return (bool) $this->row->is_navigatable;
    }

    public function clubOnly(): bool
    {
        return (bool) $this->row->is_club_only;
    }

    public function name(): string
    {
        return (string) $this->row->name;
    }

    public function icon(): int
    {
        return (int) $this->row->icon;
    }

    public function colour(): int
    {
        return (int) $this->row->colour;
    }

    public function layout(): string
    {
        return (string) $this->row->layout;
    }

    public function images(): string
    {
        return (string) $this->row->images;
    }

    public function texts(): string
    {
        return (string) $this->row->texts;
    }

    public function seasonalStart(): ?string
    {
        return $this->row->seasonal_start !== null ? (string) $this->row->seasonal_start : null;
    }

    public function seasonalLength(): int
    {
        return (int) $this->row->seasonal_length;
    }
}
