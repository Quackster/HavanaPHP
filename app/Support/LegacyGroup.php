<?php

namespace App\Support;

use Carbon\Carbon;

class LegacyGroup
{
    public readonly string $getName;

    public readonly string $getDescription;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $badge,
        private readonly ?string $alias,
        private readonly int $roomId = 0,
        /** @var array<int, int> */
        private readonly array $members = [],
        /** @var array<int, bool> */
        private readonly array $pendingMembers = [],
        private readonly int $ownerId = 0,
        private readonly string $background = 'bg_colour_08',
        private readonly int $groupType = 0,
        private readonly int $forumType = 0,
        private readonly int $forumPermission = 0,
        private readonly mixed $createdAt = null,
    ) {
        $this->getName = $name;
        $this->getDescription = $description;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getBadge(): string
    {
        return $this->badge;
    }

    public function getAlias(): string
    {
        return $this->alias ?? '';
    }

    public function getRoomId(): int
    {
        return $this->roomId;
    }

    public function isMember(int $userId): bool
    {
        return isset($this->members[$userId]);
    }

    public function isPendingMember(int $userId): bool
    {
        return isset($this->pendingMembers[$userId]);
    }

    public function hasAdministrator(int $userId): bool
    {
        return ($this->members[$userId] ?? 0) >= 2;
    }

    public function getMember(int $userId): LegacyGroupMember
    {
        return new LegacyGroupMember($this->members[$userId] ?? 0);
    }

    public function getMemberCount(bool $includePending = false): int
    {
        return count($this->members) + ($includePending ? count($this->pendingMembers) : 0);
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getBackground(): string
    {
        return $this->background;
    }

    public function getGroupType(): int
    {
        return $this->groupType;
    }

    public function getForumType(): LegacyIdValue
    {
        return new LegacyIdValue($this->forumType);
    }

    public function getForumPermission(): LegacyIdValue
    {
        return new LegacyIdValue($this->forumPermission);
    }

    public function getCreatedDate(): string
    {
        if ($this->createdAt === null || $this->createdAt === '') {
            return '';
        }

        return Carbon::parse((string) $this->createdAt)->format('M d, Y');
    }

    public function generateClickLink(): string
    {
        return $this->alias ? '/groups/'.$this->alias : '/groups/'.$this->id.'/id';
    }
}
