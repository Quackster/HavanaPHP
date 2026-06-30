<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegacyMinimailRepository
{
    /** @return array<string, mixed> */
    public function messagesContext(
        int $userId,
        string $label,
        int $conversationId,
        int $start,
        bool $unreadOnly,
        bool $minimailClient,
    ): array {
        $pageNumber = $start > 0 ? intdiv($start, 10) : 0;
        $sliceStart = $pageNumber * 10;
        $rows = $this->messagesForLabel($userId, $label, $conversationId);

        if ($unreadOnly) {
            $rows = $rows->filter(fn ($row): bool => ! (bool) $row->is_read)->values();
        }

        $total = $rows->count();
        $startPage = $start !== 0 ? $start + 1 : 1;

        return [
            'minimailLabel' => $label,
            'unreadOnly' => $unreadOnly,
            'minimailMessages' => $this->hydrateMessages($rows->slice($sliceStart, 10)->values()),
            'totalMessages' => $total,
            'minimailClient' => $minimailClient,
            'startPage' => $total === 0 ? 0 : $startPage,
            'endPage' => min($start === 0 ? 10 : $start + 10, $total),
            'showOlder' => $rows->slice(($pageNumber + 1) * 10, 1)->isNotEmpty(),
            'showOldest' => $rows->slice(($pageNumber + 2) * 10, 1)->isNotEmpty(),
            'showNewer' => $pageNumber >= 1,
            'showNewest' => $pageNumber >= 2,
        ];
    }

    /** @param Collection<int, object> $rows @return list<LegacyMinimailMessage> */
    public function hydrateMessages(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $rows->flatMap(fn ($row): array => [(int) $row->sender_id, (int) $row->to_id])->unique()->all())
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($users): ?LegacyMinimailMessage {
                $author = $users->get((int) $row->sender_id);
                $target = $users->get((int) $row->to_id);

                if (! $author instanceof User || ! $target instanceof User) {
                    return null;
                }

                return new LegacyMinimailMessage($row, new LegacyUserData($author), new LegacyUserData($target));
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return Collection<int, object> */
    private function messagesForLabel(int $userId, string $label, int $conversationId): Collection
    {
        $query = DB::table('cms_minimail');

        match (strtolower($label)) {
            'inbox' => $query->where('to_id', $userId)->where('target_id', $userId)->where('is_trash', false)->where('is_deleted', false),
            'sent' => $query->where('sender_id', $userId)->where('target_id', $userId)->where('is_trash', false)->where('is_deleted', false),
            'trash' => $query->where('target_id', $userId)->where('is_trash', true)->where('is_deleted', false),
            'conversation' => $query->where(function ($query) use ($conversationId, $userId): void {
                $query->where('conversation_id', $conversationId)->where('target_id', $userId)
                    ->orWhere(function ($query) use ($conversationId, $userId): void {
                        $query->where('sender_id', $userId)->where('id', $conversationId);
                    });
            }),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->orderByDesc('date_sent')->get();
    }
}
