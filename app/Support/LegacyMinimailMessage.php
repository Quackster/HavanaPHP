<?php

namespace App\Support;

use Carbon\Carbon;

class LegacyMinimailMessage
{
    public function __construct(
        private readonly object $row,
        private readonly LegacyUserData $author,
        private readonly LegacyUserData $target,
    ) {}

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getTargetId(): int
    {
        return (int) $this->row->target_id;
    }

    public function getToId(): int
    {
        return (int) $this->row->to_id;
    }

    public function getSenderId(): int
    {
        return (int) $this->row->sender_id;
    }

    public function isRead(): bool
    {
        return (bool) $this->row->is_read;
    }

    public function isTrash(): bool
    {
        return (bool) $this->row->is_trash;
    }

    public function getSubject(): string
    {
        return (string) $this->row->subject;
    }

    public function getFormattedSubject(): string
    {
        return LegacyMinimailText::format($this->getSubject());
    }

    public function getFormattedMessage(): string
    {
        return LegacyMinimailText::format((string) $this->row->message);
    }

    public function getDateSent(): int
    {
        return Carbon::parse($this->row->date_sent)->timestamp;
    }

    public function getDate(): string
    {
        return Carbon::parse($this->row->date_sent)->format('M j, Y g:i A');
    }

    public function getIsoDate(): string
    {
        return Carbon::parse($this->row->date_sent)->format('Y-m-d\TH:i:sO');
    }

    public function getConversationId(): int
    {
        return (int) $this->row->conversation_id;
    }

    public function getAuthor(): LegacyUserData
    {
        return $this->author;
    }

    public function getTarget(): LegacyUserData
    {
        return $this->target;
    }
}
