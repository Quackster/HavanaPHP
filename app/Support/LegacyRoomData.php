<?php

namespace App\Support;

class LegacyRoomData
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $description,
        private readonly string $ownerName,
        private readonly int $visitorsNow,
        private readonly int $visitorsMax,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getOwnerName(): string
    {
        return $this->ownerName;
    }

    public function getVisitorsNow(): int
    {
        return $this->visitorsNow;
    }

    public function getVisitorsMax(): int
    {
        return max(1, $this->visitorsMax);
    }
}
