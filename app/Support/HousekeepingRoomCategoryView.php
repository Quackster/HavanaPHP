<?php

namespace App\Support;

class HousekeepingRoomCategoryView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function orderId(): int
    {
        return (int) $this->row->order_id;
    }

    public function parentId(): int
    {
        return (int) $this->row->parent_id;
    }

    public function node(): bool
    {
        return (bool) $this->row->isnode;
    }

    public function name(): string
    {
        return (string) $this->row->name;
    }

    public function publicSpaces(): bool
    {
        return (bool) $this->row->public_spaces;
    }

    public function allowTrading(): bool
    {
        return (bool) $this->row->allow_trading;
    }

    public function minRoleAccess(): int
    {
        return (int) $this->row->minrole_access;
    }

    public function minRoleSetFlatCat(): int
    {
        return (int) $this->row->minrole_setflatcat;
    }

    public function clubOnly(): bool
    {
        return (bool) $this->row->club_only;
    }

    public function topPriority(): bool
    {
        return (bool) $this->row->is_top_priority;
    }
}
