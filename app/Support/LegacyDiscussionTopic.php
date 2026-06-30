<?php

namespace App\Support;

use Carbon\Carbon;

class LegacyDiscussionTopic
{
    public readonly string $getTopicTitle;

    public function __construct(
        private readonly int $id,
        private readonly string $topicTitle,
        private readonly int $creatorId,
        private readonly string $creatorName,
        private readonly bool $isOpen,
        private readonly bool $isStickied,
        private readonly int $views,
        private readonly int $replyCount,
        private readonly ?string $lastReplyName,
        private readonly mixed $createdAt,
        private readonly mixed $lastMessageAt,
        private readonly int $replyPages,
        private readonly bool $isNew = false,
    ) {
        $this->getTopicTitle = $topicTitle;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTopicTitle(): string
    {
        return $this->topicTitle;
    }

    public function getCreatorId(): int
    {
        return $this->creatorId;
    }

    public function getCreatorName(): string
    {
        return $this->creatorName;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function isStickied(): bool
    {
        return $this->isStickied;
    }

    /** @return list<int> */
    public function getRecentPages(): array
    {
        if ($this->replyPages <= 1) {
            return [];
        }

        return range(max(2, $this->replyPages - 2), $this->replyPages);
    }

    public function getCreatedDate(string $format): string
    {
        return $this->formatDate($this->createdAt, $format);
    }

    public function getLastMessage(string $format): string
    {
        return $this->formatDate($this->lastMessageAt, $format);
    }

    public function getLastReplyName(): string
    {
        return $this->lastReplyName ?: $this->creatorName;
    }

    public function getReplyCount(): int
    {
        return $this->replyCount;
    }

    public function getReplyPages(): int
    {
        return $this->replyPages;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function isNew(): bool
    {
        return $this->isNew;
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
