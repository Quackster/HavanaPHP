<?php

namespace App\Support;

class LegacySong
{
    public function __construct(private readonly object $row) {}

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getTitle(): string
    {
        return (string) $this->row->title;
    }

    public function getUserId(): int
    {
        return (int) $this->row->user_id;
    }

    public function getData(): string
    {
        return (string) $this->row->data;
    }
}
