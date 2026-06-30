<?php

namespace App\Support;

use Carbon\CarbonInterface;

class HousekeepingTransactionView
{
    public string $description;

    public int $costCoins;

    public int $costPixels;

    public int $amount;

    public function __construct(private readonly object $row)
    {
        $this->description = (string) $row->description;
        $this->costCoins = (int) $row->credit_cost;
        $this->costPixels = (int) $row->pixel_cost;
        $this->amount = (int) $row->amount;
    }

    public function getItemId(): int
    {
        $parts = explode(',', (string) $this->row->item_id);
        $itemId = $parts[0] ?? '';

        return is_numeric($itemId) ? (int) $itemId : 0;
    }

    public function getFormattedDate(): string
    {
        $createdAt = $this->row->created_at;

        if ($createdAt instanceof CarbonInterface) {
            return $createdAt->format('Y-m-d H:i A');
        }

        if ($createdAt === null || $createdAt === '') {
            return '';
        }

        return date('Y-m-d H:i A', strtotime((string) $createdAt) ?: 0);
    }
}
