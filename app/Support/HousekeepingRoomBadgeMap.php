<?php

namespace App\Support;

class HousekeepingRoomBadgeMap
{
    /** @param array<int, list<string>> $badges */
    public function __construct(private readonly array $badges) {}

    /** @return list<HousekeepingRoomBadgeMapEntry> */
    public function entrySet(): array
    {
        $entries = [];

        foreach ($this->badges as $roomId => $badges) {
            $entries[] = new HousekeepingRoomBadgeMapEntry($roomId, $badges);
        }

        return $entries;
    }
}
