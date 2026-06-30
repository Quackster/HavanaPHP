<?php

namespace App\Support;

class HousekeepingVoucherView
{
    public function __construct(private readonly object $row) {}

    public function voucherCode(): string
    {
        return (string) $this->row->voucher_code;
    }

    public function credits(): int
    {
        return (int) $this->row->credits;
    }

    public function expiryDate(): ?string
    {
        return $this->row->expiry_date !== null ? (string) $this->row->expiry_date : null;
    }

    public function singleUse(): bool
    {
        return (bool) $this->row->is_single_use;
    }

    public function allowNewUsers(): bool
    {
        return (bool) $this->row->allow_new_users;
    }
}
