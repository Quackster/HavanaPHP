<?php

namespace App\Support;

class HousekeepingRoomAdView
{
    public function __construct(private readonly object $row) {}

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function isLoadingAd(): bool
    {
        return (bool) $this->row->is_loading_ad;
    }

    public function getRoomId(): int
    {
        return (int) $this->row->room_id;
    }

    public function getUrl(): string
    {
        return (string) ($this->row->url ?? '');
    }

    public function getImage(): string
    {
        return (string) ($this->row->image ?? '');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->row->enabled;
    }
}
