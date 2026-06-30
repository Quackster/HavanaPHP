<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class LegacyMessenger
{
    /** @var array<int, true> */
    private array $friendIds;

    public function __construct(private readonly int $userId)
    {
        $this->friendIds = DB::table('messenger_friends')
            ->where('to_id', $this->userId)
            ->pluck('from_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    public function hasFriend(int $userId): bool
    {
        return isset($this->friendIds[$userId]);
    }
}
