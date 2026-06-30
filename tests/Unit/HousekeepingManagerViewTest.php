<?php

namespace Tests\Unit;

use App\Support\HousekeepingManagerView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HousekeepingManagerViewTest extends TestCase
{
    #[DataProvider('legacyPermissionThresholds')]
    public function test_permissions_match_legacy_rank_thresholds(string $permission, int $minimumRank): void
    {
        $manager = new HousekeepingManagerView;

        $this->assertFalse($manager->hasPermission($minimumRank - 1, $permission));
        $this->assertTrue($manager->hasPermission($minimumRank, $permission));
        $this->assertTrue($manager->hasPermission(8, $permission));
    }

    public function test_unknown_permissions_are_denied(): void
    {
        $manager = new HousekeepingManagerView;

        $this->assertFalse($manager->hasPermission(8, 'unknown/permission'));
        $this->assertFalse($manager->hasPermission(null, 'root/login'));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function legacyPermissionThresholds(): array
    {
        return [
            'root/login' => ['root/login', 5],
            'transaction/lookup' => ['transaction/lookup', 5],
            'marketplace/log_check' => ['marketplace/log_check', 5],
            'marketplace/user_log' => ['marketplace/user_log', 5],
            'bans' => ['bans', 5],
            'user/search' => ['user/search', 8],
            'user/edit' => ['user/edit', 8],
            'user/create' => ['user/create', 8],
            'articles/create' => ['articles/create', 5],
            'articles/edit_any' => ['articles/edit_any', 8],
            'articles/edit_own' => ['articles/edit_own', 5],
            'articles/delete_any' => ['articles/delete_any', 8],
            'articles/delete_own' => ['articles/delete_own', 5],
            'room_ads' => ['room_ads', 8],
            'room_badges' => ['room_badges', 6],
            'configuration' => ['configuration', 8],
            'infobus' => ['infobus', 6],
            'infobus/delete_any' => ['infobus/delete_any', 8],
            'infobus/delete_own' => ['infobus/delete_own', 6],
            'catalogue/edit_frontpage' => ['catalogue/edit_frontpage', 6],
            'catalogue/manage' => ['catalogue/manage', 8],
            'item_definitions/manage' => ['item_definitions/manage', 8],
            'vouchers/manage' => ['vouchers/manage', 8],
            'wordfilter/manage' => ['wordfilter/manage', 8],
            'recycler/manage' => ['recycler/manage', 8],
            'room_categories/manage' => ['room_categories/manage', 8],
            'room_models/manage' => ['room_models/manage', 8],
            'rooms/manage' => ['rooms/manage', 8],
            'groups/manage' => ['groups/manage', 8],
            'user/imitate' => ['user/imitate', 8],
            'user/matches' => ['user/matches', 8],
            'badges' => ['badges', 6],
        ];
    }
}
