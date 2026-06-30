<?php

namespace App\Support;

class HousekeepingCatalogueCollectableView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->store_page;
    }

    public function storePageId(): int
    {
        return (int) $this->row->store_page;
    }

    public function adminPageId(): int
    {
        return (int) $this->row->admin_page;
    }

    public function expiry(): int
    {
        return (int) $this->row->expiry;
    }

    public function lifetime(): int
    {
        return (int) $this->row->lifetime;
    }

    public function currentPosition(): int
    {
        return (int) $this->row->current_position;
    }

    public function classNames(): string
    {
        return (string) $this->row->class_names;
    }
}
