<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;

class HousekeepingPlayerView
{
    public int $id;

    public string $name;

    public string $email;

    public string $figure;

    public string $motto;

    public string $mission;

    public int $credits;

    public int $pixels;

    private HousekeepingRank $rank;

    private mixed $lastOnline;

    private mixed $createdAt;

    public function __construct(User $user)
    {
        $this->id = (int) $user->id;
        $this->name = (string) $user->username;
        $this->email = (string) $user->email;
        $this->figure = (string) $user->figure;
        $this->motto = (string) $user->motto;
        $this->mission = (string) $user->motto;
        $this->credits = (int) $user->credits;
        $this->pixels = (int) $user->pixels;
        $this->rank = new HousekeepingRank((int) $user->rank);
        $this->lastOnline = $user->last_online;
        $this->createdAt = $user->created_at;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRank(): HousekeepingRank
    {
        return $this->rank;
    }

    public function formatLastOnline(string $format): string
    {
        return $this->formatDate($this->lastOnline, $format);
    }

    public function formatJoinDate(string $format): string
    {
        return $this->formatDate($this->createdAt, $format);
    }

    public function getReadableLastOnline(): string
    {
        return $this->formatLastOnline('d-m-Y H:i:s');
    }

    public function getReadableJoinDate(): string
    {
        return $this->formatJoinDate('d-m-Y H:i:s');
    }

    private function formatDate(mixed $value, string $format): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format($format);
        }

        if ($value === null || $value === '') {
            return '';
        }

        return date($format, strtotime((string) $value) ?: 0);
    }
}
