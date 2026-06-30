<?php

namespace App\Support;

class HousekeepingItemDefinitionView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function sprite(): string
    {
        return (string) $this->row->sprite;
    }

    public function name(): string
    {
        return (string) $this->row->name;
    }

    public function description(): string
    {
        return (string) $this->row->description;
    }

    public function spriteId(): int
    {
        return (int) $this->row->sprite_id;
    }

    public function length(): int
    {
        return (int) $this->row->length;
    }

    public function width(): int
    {
        return (int) $this->row->width;
    }

    public function topHeight(): float
    {
        return (float) $this->row->top_height;
    }

    public function maxStatus(): string
    {
        return (string) $this->row->max_status;
    }

    public function behaviour(): string
    {
        return (string) $this->row->behaviour;
    }

    public function interactor(): string
    {
        return (string) $this->row->interactor;
    }

    public function tradable(): bool
    {
        return (bool) $this->row->is_tradable;
    }

    public function recyclable(): bool
    {
        return (bool) $this->row->is_recyclable;
    }

    public function drinkIds(): ?string
    {
        return $this->row->drink_ids !== null ? (string) $this->row->drink_ids : null;
    }

    public function rentalTime(): int
    {
        return (int) $this->row->rental_time;
    }

    public function allowedRotations(): string
    {
        return (string) $this->row->allowed_rotations;
    }

    public function heights(): ?string
    {
        return $this->row->heights !== null ? (string) $this->row->heights : null;
    }
}
