<?php

namespace App\Support;

class LegacyHighscoreEntry
{
    public function __construct(
        private readonly int $position,
        private readonly string $playerName,
        private readonly int $score,
    ) {}

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getScore(): int
    {
        return $this->score;
    }
}
