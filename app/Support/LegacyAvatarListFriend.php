<?php

namespace App\Support;

use Carbon\Carbon;

class LegacyAvatarListFriend
{
    public function __construct(
        private readonly int $userId,
        private readonly string $username,
        private readonly string $figure,
        private readonly mixed $lastOnline,
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getFigure(): string
    {
        return $this->figure;
    }

    public function getFormatLastOnline(string $format): string
    {
        if ($this->lastOnline === null || $this->lastOnline === '') {
            return '';
        }

        $phpFormat = str_replace(['dd', 'MM', 'yyyy'], ['d', 'm', 'Y'], $format);

        return Carbon::parse($this->lastOnline)->format($phpFormat);
    }
}
