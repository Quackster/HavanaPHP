<?php

namespace App\Support;

class HousekeepingWordfilterView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function word(): string
    {
        return (string) $this->row->word;
    }

    public function bannable(): bool
    {
        return (bool) $this->row->is_bannable;
    }

    public function filterable(): bool
    {
        return (bool) $this->row->is_filterable;
    }
}
