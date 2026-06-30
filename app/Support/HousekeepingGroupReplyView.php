<?php

namespace App\Support;

class HousekeepingGroupReplyView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
    }

    public function threadId(): int
    {
        return (int) $this->row->thread_id;
    }

    public function topicTitle(): string
    {
        return (string) $this->row->topic_title;
    }

    public function posterId(): int
    {
        return (int) $this->row->poster_id;
    }

    public function posterName(): string
    {
        return (string) ($this->row->poster_name ?? '');
    }

    public function message(): string
    {
        return (string) $this->row->message;
    }

    public function edited(): bool
    {
        return (bool) $this->row->is_edited;
    }

    public function deleted(): bool
    {
        return (bool) $this->row->is_deleted;
    }

    public function createdAt(): string
    {
        return (string) $this->row->created_at;
    }

    public function modifiedAt(): string
    {
        return (string) $this->row->modified_at;
    }
}
