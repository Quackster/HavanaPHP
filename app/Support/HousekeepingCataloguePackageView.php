<?php

namespace App\Support;

class HousekeepingCataloguePackageView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function saleCode(): string
    {
        return (string) $this->row->salecode;
    }

    public function definitionId(): int
    {
        return (int) $this->row->definition_id;
    }

    public function specialSpriteId(): string
    {
        return (string) $this->row->special_sprite_id;
    }

    public function amount(): int
    {
        return (int) $this->row->amount;
    }
}
