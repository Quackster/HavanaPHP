<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

class LegacyEvent
{
    public function __construct(
        private readonly object $row,
        private readonly object $room,
        private readonly User $user,
    ) {}

    public function getName(): string
    {
        return (string) $this->row->name;
    }

    public function getDescription(): string
    {
        return (string) $this->row->description;
    }

    public function getFriendlyDate(): string
    {
        return Carbon::createFromTimestamp((int) $this->row->expire_time)->format('M j, Y g:i:s A');
    }

    public function getRoomData(): object
    {
        return new class($this->room)
        {
            public function __construct(private readonly object $room) {}

            public function getId(): int
            {
                return (int) $this->room->id;
            }

            public function getName(): string
            {
                return (string) $this->room->name;
            }

            public function getVisitorsNow(): int
            {
                return (int) $this->room->visitors_now;
            }

            public function getVisitorsMax(): int
            {
                return (int) $this->room->visitors_max;
            }
        };
    }

    public function getUserInfo(): LegacyUserData
    {
        return new LegacyUserData($this->user);
    }
}
