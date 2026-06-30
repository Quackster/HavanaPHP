<?php

namespace App\Support;

class LegacyMessengerFriend
{
    public function __construct(
        private readonly int $userId,
        private readonly string $username,
        private readonly int $categoryId,
        private readonly mixed $lastOnline,
        private readonly bool $online = false,
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getFormattedLastOnline(): string
    {
        if ($this->lastOnline === null || $this->lastOnline === '') {
            return '';
        }

        $timestamp = strtotime((string) $this->lastOnline);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : (string) $this->lastOnline;
    }

    public function getLastOnline(): int
    {
        if ($this->lastOnline === null || $this->lastOnline === '') {
            return 0;
        }

        return strtotime((string) $this->lastOnline) ?: 0;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }
}
