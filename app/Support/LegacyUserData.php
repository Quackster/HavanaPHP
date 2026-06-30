<?php

namespace App\Support;

use App\Models\User;

class LegacyUserData
{
    public readonly int $id;

    public readonly int $credits;

    public readonly string $figure;

    public readonly string $getMotto;

    public function __construct(
        private readonly User $user,
    ) {
        $this->id = (int) $user->id;
        $this->credits = (int) $user->credits;
        $this->figure = (string) $user->figure;
        $this->getMotto = (string) $user->motto;
    }

    public function getName(): string
    {
        return (string) $this->user->username;
    }

    public function getId(): int
    {
        return (int) $this->user->id;
    }

    public function getFigure(): string
    {
        return (string) $this->user->figure;
    }

    public function getMotto(): string
    {
        return (string) $this->user->motto;
    }

    public function getCreatedAt(): string
    {
        return $this->user->created_at?->format('M j, Y') ?? '';
    }

    public function getFormattedLastOnline(): string
    {
        return $this->user->last_online?->format('M j, Y') ?? '';
    }

    public function getFavouriteGroupId(): int
    {
        return (int) $this->user->favourite_group;
    }

    public function getRank(): LegacyIdValue
    {
        return new LegacyIdValue((int) $this->user->rank);
    }

    public function hasClubSubscription(): bool
    {
        return (int) $this->user->club_expiration > time();
    }

    public function isOnline(): bool
    {
        return (bool) $this->user->is_online;
    }

    public function isProfileVisible(): bool
    {
        return (bool) $this->user->profile_visible;
    }
}
