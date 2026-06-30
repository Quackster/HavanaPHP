<?php

namespace App\Support;

class HousekeepingRank
{
    public function __construct(private readonly int $rankId) {}

    public function getRankId(): int
    {
        return $this->rankId;
    }

    public function getName(): string
    {
        return match ($this->rankId) {
            0 => 'RANKLESS',
            1 => 'NORMAL',
            2 => 'GUIDE',
            3 => 'HOBBA',
            4 => 'SUPERHOBBA',
            5 => 'MODERATOR',
            6 => 'COMMUNITY_MANAGER',
            7 => 'HOTEL_MANAGER',
            8 => 'ADMINISTRATOR',
            default => 'RANK_'.$this->rankId,
        };
    }
}
