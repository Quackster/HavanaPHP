<?php

namespace App\Support;

class HousekeepingGroupThreadView
{
    public function __construct(private readonly object $row) {}

    public function id(): int
    {
        return (int) $this->row->id;
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

    public function open(): bool
    {
        return (bool) $this->row->is_open;
    }

    public function stickied(): bool
    {
        return (bool) $this->row->is_stickied;
    }

    public function views(): int
    {
        return (int) $this->row->views;
    }

    public function createdAt(): string
    {
        return (string) $this->row->created_at;
    }

    public function modifiedAt(): string
    {
        return (string) $this->row->modified_at;
    }

    public function replyCount(): int
    {
        return (int) ($this->row->reply_count ?? 0);
    }
}
