<?php

namespace App\Support;

use Carbon\Carbon;

class LegacyDiscussionReply
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly string $username,
        private readonly string $figure,
        private readonly string $message,
        private readonly bool $isEdited,
        private readonly bool $isDeleted,
        private readonly mixed $createdAt,
        private readonly mixed $modifiedAt,
        private readonly bool $isOnline = false,
        private readonly int $forumMessages = 0,
        private readonly int $groupId = 0,
        private readonly string $groupBadge = '',
        private readonly string $equippedBadge = '',
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFormattedMessage(): string
    {
        return nl2br(e($this->message), false);
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function getForumMessages(): int
    {
        return $this->forumMessages;
    }

    public function hasGroupBadge(): bool
    {
        return $this->groupId > 0 && $this->groupBadge !== '';
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getGroupBadge(): string
    {
        return $this->groupBadge;
    }

    public function hasBadge(): bool
    {
        return $this->equippedBadge !== '';
    }

    public function getEquippedBadge(): string
    {
        return $this->equippedBadge;
    }

    public function isEdited(): bool
    {
        return $this->isEdited;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function getCreatedDate(string $format): string
    {
        return $this->formatDate($this->createdAt, $format);
    }

    public function getEditedDate(string $format): string
    {
        return $this->formatDate($this->modifiedAt, $format);
    }

    private function formatDate(mixed $date, string $format): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return match ($format) {
            'MMM dd, yyyy' => $carbon->format('M d, Y'),
            'h:mm a' => $carbon->format('g:i A'),
            default => $carbon->format('M d, Y'),
        };
    }
}
