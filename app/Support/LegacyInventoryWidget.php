<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LegacyInventoryWidget
{
    public function __construct(
        private readonly object $row,
        private readonly LegacyStickerProduct $product,
        private int $amount = 1,
    ) {}

    public function getId(): int
    {
        return (int) $this->row->id;
    }

    public function getUserId(): int
    {
        return (int) $this->row->user_id;
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

    public function getSkin(): string
    {
        return LegacyNoteWidget::skinName((int) $this->row->skin_id);
    }

    public function getSkinId(): int
    {
        return (int) $this->row->skin_id;
    }

    public function getStickerId(): int
    {
        return (int) $this->row->sticker_id;
    }

    public function getName(): string
    {
        return $this->product->getName();
    }

    public function getProduct(): LegacyStickerProduct
    {
        return $this->product;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function isPlaced(): bool
    {
        return (bool) $this->row->is_placed;
    }

    public function getFormattedText(): string
    {
        return LegacyNoteWidget::formatText((string) $this->row->text);
    }

    public function hasRated(int $userId): bool
    {
        return DB::table('homes_ratings')
            ->where('user_id', $userId)
            ->where('home_id', $this->getUserId())
            ->exists();
    }

    public function getAverageRating(): int
    {
        $average = DB::table('homes_ratings')->where('home_id', $this->getUserId())->avg('rating');

        return (int) ($average ?? 0);
    }

    public function getRatingPixels(): int
    {
        $rating = $this->getAverageRating();

        if ($rating <= 0) {
            $rating = 1;
        }

        return (int) round($rating * 150 / 5);
    }

    public function getVoteCount(): int
    {
        return DB::table('homes_ratings')->where('home_id', $this->getUserId())->count();
    }

    public function getHighVoteCount(): int
    {
        return DB::table('homes_ratings')
            ->where('home_id', $this->getUserId())
            ->where('rating', '>=', 4)
            ->count();
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

    /** @return list<LegacyBadge> */
    public function getFirstBadges(): array
    {
        return $this->badgePages(true)[0] ?? [];
    }

    public function getBadgeList(): LegacyListView
    {
        return new LegacyListView($this->badgePages());
    }

    /**
     * @return list<list<LegacyBadge>>
     */
    private function badgePages(bool $emptyFirstPage = false): array
    {
        $badges = DB::table('users_badges')
            ->where('user_id', $this->getUserId())
            ->pluck('badge')
            ->map(fn ($badge): LegacyBadge => new LegacyBadge((string) $badge))
            ->all();

        $pages = array_chunk($badges, 16);

        if ($emptyFirstPage && $pages === []) {
            return [[]];
        }

        return $pages;
    }

    private function getGroupId(): int
    {
        return (int) $this->row->group_id;
    }

    private function isGroupWidget(): bool
    {
        return $this->getProduct()->getTypeId() === 5 || $this->getGroupId() > 0;
    }
}
