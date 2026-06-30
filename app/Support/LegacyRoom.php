<?php

namespace App\Support;

class LegacyRoom
{
    public function __construct(private readonly LegacyRoomData $data) {}

    public function getData(): LegacyRoomData
    {
        return $this->data;
    }

    public function getId(): int
    {
        return $this->data->getId();
    }
}
