<?php

namespace App\Support;

class HousekeepingCatalogueItemView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function saleCode(): string
    {
        return (string) $this->row->sale_code;
    }

    public function pageId(): string
    {
        return (string) $this->row->page_id;
    }

    public function orderId(): int
    {
        return (int) $this->row->order_id;
    }

    public function priceCoins(): int
    {
        return (int) $this->row->price_coins;
    }

    public function pricePixels(): int
    {
        return (int) $this->row->price_pixels;
    }

    public function seasonalCoins(): int
    {
        return (int) $this->row->seasonal_coins;
    }

    public function seasonalPixels(): int
    {
        return (int) $this->row->seasonal_pixels;
    }

    public function hidden(): bool
    {
        return (bool) $this->row->hidden;
    }

    public function amount(): int
    {
        return (int) $this->row->amount;
    }

    public function definitionId(): int
    {
        return (int) $this->row->definition_id;
    }

    public function itemSpecialSpriteId(): string
    {
        return (string) $this->row->item_specialspriteid;
    }

    public function packageItem(): bool
    {
        return (bool) $this->row->is_package;
    }

    public function activeAt(): ?string
    {
        return $this->row->active_at !== null ? (string) $this->row->active_at : null;
    }
}
