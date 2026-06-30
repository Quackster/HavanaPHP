<?php

namespace App\Support;

class HousekeepingManagerView
{
    /** @var array<string, int> */
    private array $permissions = [
        'root/login' => 5,
        'transaction/lookup' => 5,
        'marketplace/log_check' => 5,
        'marketplace/user_log' => 5,
        'bans' => 5,
        'user/search' => 8,
        'user/edit' => 8,
        'user/create' => 8,
        'articles/create' => 5,
        'articles/edit_any' => 8,
        'articles/edit_own' => 5,
        'articles/delete_any' => 8,
        'articles/delete_own' => 5,
        'room_ads' => 8,
        'room_badges' => 6,
        'configuration' => 8,
        'infobus' => 6,
        'infobus/delete_any' => 8,
        'infobus/delete_own' => 6,
        'catalogue/edit_frontpage' => 6,
        'catalogue/manage' => 8,
        'item_definitions/manage' => 8,
        'vouchers/manage' => 8,
        'wordfilter/manage' => 8,
        'recycler/manage' => 8,
        'room_categories/manage' => 8,
        'room_models/manage' => 8,
        'rooms/manage' => 8,
        'groups/manage' => 8,
        'user/imitate' => 8,
        'user/matches' => 8,
        'badges' => 6,
    ];

    public function hasPermission(HousekeepingRank|int|null $rank, string $permission): bool
    {
        if (! array_key_exists($permission, $this->permissions)) {
            return false;
        }

        $rankId = $rank instanceof HousekeepingRank ? $rank->getRankId() : (int) $rank;

        return $rankId >= $this->permissions[$permission];
    }
}
