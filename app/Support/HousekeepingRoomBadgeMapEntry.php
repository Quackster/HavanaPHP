<?php

namespace App\Support;

class HousekeepingRoomBadgeMapEntry
{
    /** @param list<string> $badges */
    public function __construct(
        private readonly int $roomId,
        private readonly array $badges,
    ) {}

    public function getKey(): int
    {
        return $this->roomId;
    }

    /** @return list<string> */
    public function getValue(): array
    {
        return $this->badges;
    }
}
