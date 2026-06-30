<?php

namespace App\Support;

class HousekeepingVoucherHistoryView
{
    public function __construct(private readonly object $row) {}

    public function voucherCode(): string
    {
        return (string) $this->row->voucher_code;
    }

    public function userId(): int
    {
        return (int) $this->row->user_id;
    }

    public function usedAt(): ?string
    {
        return $this->row->used_at !== null ? (string) $this->row->used_at : null;
    }

    public function creditsRedeemed(): ?int
    {
        return $this->row->credits_redeemed !== null ? (int) $this->row->credits_redeemed : null;
    }

    public function itemsRedeemed(): ?string
    {
        return $this->row->items_redeemed !== null ? (string) $this->row->items_redeemed : null;
    }
}
