<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LegacyGuestbookWidget
{
    private const HOME_GUESTBOOK_STICKER_ID = 10200;

    private const GROUP_GUESTBOOK_STICKER_ID = 11100;

    public function __construct(
        private readonly object $row,
    ) {}

    public static function find(int $widgetId): ?self
    {
        $row = DB::table('cms_stickers')->where('id', $widgetId)->first();

        if (! $row || ! in_array((int) $row->sticker_id, [self::HOME_GUESTBOOK_STICKER_ID, self::GROUP_GUESTBOOK_STICKER_ID], true)) {
            return null;
        }

        return new self($row);
    }

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getX(): int
    {
        return (int) $this->row->x;
    }

    public function getY(): int
    {
        return (int) $this->row->y;
    }

    public function getZ(): int
    {
        return (int) $this->row->z;
    }

    public function getSkin(): int
    {
        return (int) $this->row->skin_id;
    }

    public function getUserId(): int
    {
        return (int) $this->row->user_id;
    }

    public function getGroupId(): int
    {
        return (int) $this->row->group_id;
    }

    public function isPlaced(): bool
    {
        return (bool) $this->row->is_placed;
    }

    public function isGroupWidget(): bool
    {
        return (int) $this->row->sticker_id === self::GROUP_GUESTBOOK_STICKER_ID || $this->getGroupId() > 0;
    }

    public function getGuestbookState(): string
    {
        $state = strtolower((string) $this->row->extra_data);

        return in_array($state, ['public', 'private'], true) ? $state : 'public';
    }

    public function isPostingAllowed(int $userId): bool
    {
        if ($this->getGuestbookState() === 'public') {
            return true;
        }

        if ($this->isGroupWidget()) {
            return DB::table('groups_memberships')
                ->where('group_id', $this->getGroupId())
                ->where('user_id', $userId)
                ->where('is_pending', false)
                ->exists();
        }

        return $userId === $this->getUserId() || DB::table('messenger_friends')
            ->where(function ($query) use ($userId): void {
                $query->where('from_id', $userId)->where('to_id', $this->getUserId());
            })
            ->orWhere(function ($query) use ($userId): void {
                $query->where('from_id', $this->getUserId())->where('to_id', $userId);
            })
            ->exists();
    }

    /** @return list<LegacyGuestbookEntry> */
    public function getGuestbookEntries(): array
    {
        $query = DB::table('cms_guestbook_entries')->orderByDesc('created_at')->limit(500);

        if ($this->isGroupWidget()) {
            $query->where('group_id', $this->getGroupId());
        } else {
            $query->where('home_id', $this->getUserId());
        }

        return $query->get()->map(fn (object $row): LegacyGuestbookEntry => LegacyGuestbookEntry::fromRow($row))->all();
    }

    public function canDeleteEntries(int $userId): bool
    {
        if ($this->isGroupWidget()) {
            return (int) DB::table('groups_details')->where('id', $this->getGroupId())->value('owner_id') === $userId;
        }

        return $userId === $this->getUserId();
    }

    public function ownerId(): int
    {
        if ($this->isGroupWidget()) {
            return (int) DB::table('groups_details')->where('id', $this->getGroupId())->value('owner_id');
        }

        return $this->getUserId();
    }

    public function toggleGuestbookState(): string
    {
        $state = $this->getGuestbookState() === 'private' ? 'public' : 'private';

        DB::table('cms_stickers')->where('id', $this->getId())->update(['extra_data' => $state]);
        $this->row->extra_data = $state;

        return $state;
    }
}
