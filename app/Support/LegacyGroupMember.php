<?php

namespace App\Support;

use App\Models\User;

class LegacyGroupMember
{
    public function __construct(
        private readonly int $rankId,
        private readonly int $favouriteGroupId = 0,
        private readonly ?User $user = null,
    ) {}

    public function getMemberRank(): self
    {
        return $this;
    }

    public function getRankId(): int
    {
        return $this->rankId;
    }

    public function isFavourite(int $groupId): bool
    {
        return $this->favouriteGroupId === $groupId;
    }

    public function getUser(): ?LegacyUserData
    {
        return $this->user instanceof User ? new LegacyUserData($this->user) : null;
    }
}
