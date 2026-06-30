<?php

namespace App\Support;

class LegacyTagResult
{
    /** @param list<string> $tagList */
    public function __construct(
        private readonly int $userId,
        private readonly LegacyUserData $userData,
        private readonly array $tagList,
    ) {}

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getGroupId(): int
    {
        return 0;
    }

    public function getUserData(): LegacyUserData
    {
        return $this->userData;
    }

    /** @return list<string> */
    public function getTagList(): array
    {
        return $this->tagList;
    }
}
