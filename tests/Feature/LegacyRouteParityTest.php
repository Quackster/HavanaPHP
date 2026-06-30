<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyRouteParityTest extends TestCase
{
    public function test_active_legacy_routes_have_laravel_route_patterns(): void
    {
        $legacyRoutes = $this->legacyRoutePatterns();
        $laravelRoutes = $this->laravelRoutePatterns();

        $missing = array_values(array_diff($legacyRoutes, $laravelRoutes));

        $this->assertSame([], $missing, 'Missing Laravel route patterns for active legacy routes.');
    }

    public function test_legacy_root_and_housekeeping_slash_aliases_are_reachable(): void
    {
        $this->get('/')->assertOk();
        $this->get('/allseeingeye/hk')->assertOk();
        $this->get('/allseeingeye/hk/')->assertOk();
    }

    public function test_group_and_discussion_legacy_route_surface_is_present(): void
    {
        $expected = [
            '/discussions/actions/confirm_delete_topic',
            '/discussions/actions/deletepost',
            '/discussions/actions/deletetopic',
            '/discussions/actions/newtopic',
            '/discussions/actions/opentopicsettings',
            '/discussions/actions/pingsession',
            '/discussions/actions/previewpost',
            '/discussions/actions/previewtopic',
            '/discussions/actions/savepost',
            '/discussions/actions/savetopic',
            '/discussions/actions/savetopicsettings',
            '/discussions/actions/updatepost',
            '/grouppurchase/group_create_form',
            '/grouppurchase/purchase_ajax',
            '/grouppurchase/purchase_confirmation',
            '/groups/*',
            '/groups/*/discussions',
            '/groups/*/discussions/*/id',
            '/groups/*/discussions/*/id/page/*',
            '/groups/*/discussions/page/*',
            '/groups/*/id',
            '/groups/*/id/discussions',
            '/groups/*/id/discussions/*/id',
            '/groups/*/id/discussions/*/id/page/*',
            '/groups/*/id/discussions/page/*',
            '/groups/actions/cancelEditingSession',
            '/groups/actions/check_group_url',
            '/groups/actions/confirm_delete_group',
            '/groups/actions/confirm_deselect_favorite',
            '/groups/actions/confirm_leave',
            '/groups/actions/confirm_select_favorite',
            '/groups/actions/delete_group',
            '/groups/actions/deselect_favorite',
            '/groups/actions/group_settings',
            '/groups/actions/join',
            '/groups/actions/leave',
            '/groups/actions/saveEditingSession',
            '/groups/actions/select_favorite',
            '/groups/actions/show_badge_editor',
            '/groups/actions/startEditingSession/*',
            '/groups/actions/update_group_badge',
            '/groups/actions/update_group_settings',
            '/myhabbo/groups/batch/accept',
            '/myhabbo/groups/batch/confirm_accept',
            '/myhabbo/groups/batch/confirm_decline',
            '/myhabbo/groups/batch/confirm_give_rights',
            '/myhabbo/groups/batch/confirm_remove',
            '/myhabbo/groups/batch/confirm_revoke_rights',
            '/myhabbo/groups/batch/decline',
            '/myhabbo/groups/batch/give_rights',
            '/myhabbo/groups/batch/remove',
            '/myhabbo/groups/batch/revoke_rights',
            '/myhabbo/groups/groupinfo',
            '/myhabbo/groups/memberlist',
            '/myhabbo/tag/addgrouptag',
            '/myhabbo/tag/listgrouptags',
            '/myhabbo/tag/removegrouptag',
        ];

        $legacyRoutes = $this->legacyRoutePatterns();
        $laravelRoutes = $this->laravelRoutePatterns();

        $this->assertSame([], array_values(array_diff($expected, $legacyRoutes)), 'Expected group/discussion route missing from Java legacy source.');
        $this->assertSame([], array_values(array_diff($expected, $laravelRoutes)), 'Expected group/discussion route missing from Laravel route table.');
    }

    public function test_home_widget_store_and_trax_legacy_route_surface_is_present(): void
    {
        $expected = [
            '/home/*',
            '/home/*/id',
            '/myhabbo/avatarlist/avatarinfo',
            '/myhabbo/avatarlist/friendsearchpaging',
            '/myhabbo/avatarlist/membersearchpaging',
            '/myhabbo/badgelist/badgepaging',
            '/myhabbo/cancel/*',
            '/myhabbo/groups/groupinfo',
            '/myhabbo/guestbook/add',
            '/myhabbo/guestbook/configure',
            '/myhabbo/guestbook/preview',
            '/myhabbo/guestbook/remove',
            '/myhabbo/linktool/search',
            '/myhabbo/noteeditor/editor',
            '/myhabbo/noteeditor/place',
            '/myhabbo/noteeditor/preview',
            '/myhabbo/rating/rate',
            '/myhabbo/rating/reset_ratings',
            '/myhabbo/save',
            '/myhabbo/startSession/*',
            '/myhabbo/sticker/place_sticker',
            '/myhabbo/sticker/remove_sticker',
            '/myhabbo/stickie/delete',
            '/myhabbo/stickie/edit',
            '/myhabbo/store/background_warning',
            '/myhabbo/store/inventory',
            '/myhabbo/store/inventory_items',
            '/myhabbo/store/inventory_preview',
            '/myhabbo/store/items',
            '/myhabbo/store/main',
            '/myhabbo/store/preview',
            '/myhabbo/store/purchase_backgrounds',
            '/myhabbo/store/purchase_confirm',
            '/myhabbo/store/purchase_stickers',
            '/myhabbo/store/purchase_stickie_notes',
            '/myhabbo/tag/list',
            '/myhabbo/traxplayer/select_song',
            '/myhabbo/widget/add',
            '/myhabbo/widget/delete',
            '/myhabbo/widget/edit',
            '/trax/song/*',
        ];

        $legacyRoutes = $this->legacyRoutePatterns();
        $laravelRoutes = $this->laravelRoutePatterns();

        $this->assertSame([], array_values(array_diff($expected, $legacyRoutes)), 'Expected home/widget/store/trax route missing from Java legacy source.');
        $this->assertSame([], array_values(array_diff($expected, $laravelRoutes)), 'Expected home/widget/store/trax route missing from Laravel route table.');
    }

    public function test_habblet_ajax_legacy_route_surface_is_present(): void
    {
        $expected = [
            '/components/roomNavigation',
            '/friendmanagement/ajax/createcategory',
            '/friendmanagement/ajax/deletecategory',
            '/friendmanagement/ajax/deletefriends',
            '/friendmanagement/ajax/editCategory',
            '/friendmanagement/ajax/movefriends',
            '/friendmanagement/ajax/updatecategoryoptions',
            '/friendmanagement/ajax/viewcategory',
            '/habblet/ajax/addFriend',
            '/habblet/ajax/clear_hand',
            '/habblet/ajax/collectiblesConfirm',
            '/habblet/ajax/collectiblesPurchase',
            '/habblet/ajax/confirmAddFriend',
            '/habblet/ajax/giftqueueHide',
            '/habblet/ajax/habboclub_enddate',
            '/habblet/ajax/habboclub_gift',
            '/habblet/ajax/load_events',
            '/habblet/ajax/mgmgetinvitelink',
            '/habblet/ajax/namecheck',
            '/habblet/ajax/nextgift',
            '/habblet/ajax/preview_news_article',
            '/habblet/ajax/redeemvoucher',
            '/habblet/ajax/removeFeedItem',
            '/habblet/ajax/roomselectionConfirm',
            '/habblet/ajax/roomselectionCreate',
            '/habblet/ajax/roomselectionHide',
            '/habblet/ajax/tagfight',
            '/habblet/ajax/tagmatch',
            '/habblet/ajax/tagsearch',
            '/habblet/ajax/token_generate',
            '/habblet/ajax/updatemotto',
            '/habblet/cproxy',
            '/habblet/habbosearchcontent',
            '/habblet/mytagslist',
            '/habblet/personalhighscores',
            '/habblet/proxy',
            '/habboclub/habboclub_confirm',
            '/habboclub/habboclub_reminder_remove',
            '/habboclub/habboclub_subscribe',
            '/myhabbo/friends/add',
            '/myhabbo/tag/add',
            '/myhabbo/tag/remove',
            '/remove_all_tags',
            '/tag/*',
        ];

        $legacyRoutes = $this->legacyRoutePatterns();
        $laravelRoutes = $this->laravelRoutePatterns();

        $this->assertSame([], array_values(array_diff($expected, $legacyRoutes)), 'Expected habblet/AJAX route missing from Java legacy source.');
        $this->assertSame([], array_values(array_diff($expected, $laravelRoutes)), 'Expected habblet/AJAX route missing from Laravel route table.');
    }

    public function test_housekeeping_legacy_route_surface_matches_laravel(): void
    {
        $legacyRoutes = $this->housekeepingRoutes($this->legacyRoutePatterns());
        $laravelRoutes = $this->housekeepingRoutes($this->laravelRoutePatterns());

        $this->assertCount(80, $legacyRoutes, 'Unexpected housekeeping route count in Java legacy source.');
        $this->assertSame($legacyRoutes, $laravelRoutes, 'Laravel housekeeping routes differ from Java legacy source.');
    }

    /** @return list<string> */
    private function legacyRoutePatterns(): array
    {
        $routesFile = base_path('../Havana/Havana-Web/src/main/java/org/alexdev/http/Routes.java');
        $this->assertFileExists($routesFile);

        $routes = [];

        foreach (file($routesFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if (str_starts_with($line, '//') || ! str_contains($line, 'RouteManager.addRoute(')) {
                continue;
            }

            if (preg_match('/new String\[\]\s*\{([^}]+)\}/', $line, $match) === 1) {
                preg_match_all('/"([^"]*)"/', $match[1], $arrayRoutes);

                foreach ($arrayRoutes[1] as $route) {
                    $routes[] = $this->normaliseRoutePattern($route);
                }

                continue;
            }

            if (preg_match('/RouteManager\.addRoute\((.+?),\s*/', $line, $match) !== 1) {
                continue;
            }

            $expression = trim($match[1]);

            if (preg_match('/^"([^"]*)"$/', $expression, $routeMatch) === 1) {
                $routes[] = $this->normaliseRoutePattern($routeMatch[1]);

                continue;
            }

            if (str_contains($expression, 'HOUSEKEEPING_PATH')) {
                $routes[] = $this->normaliseRoutePattern($this->housekeepingRoute($expression));
            }
        }

        return $this->uniqueSorted($routes);
    }

    /** @return list<string> */
    private function laravelRoutePatterns(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $routes[] = $this->normaliseRoutePattern('/'.$route->uri());
        }

        return $this->uniqueSorted($routes);
    }

    private function housekeepingRoute(string $expression): string
    {
        $route = str_replace([
            '" + HOUSEKEEPING_PATH + "',
            '" + HOUSEKEEPING_PATH',
            'HOUSEKEEPING_PATH + "',
        ], 'allseeingeye/hk', $expression);

        return str_replace(['"', ' + '], '', $route);
    }

    private function normaliseRoutePattern(string $route): string
    {
        $route = trim($route);
        $route = $route === '' ? '/' : $route;
        $route = '/'.ltrim($route, '/');
        $route = preg_replace('/\{[^}]+\?\}/', '*', $route) ?? $route;
        $route = preg_replace('/\{[^}]+\}/', '*', $route) ?? $route;
        $route = str_replace('*-*', '*', $route);

        return rtrim($route, '/') === '' ? '/' : rtrim($route, '/');
    }

    /**
     * @param  list<string>  $routes
     * @return list<string>
     */
    private function housekeepingRoutes(array $routes): array
    {
        return array_values(array_filter(
            $routes,
            static fn (string $route): bool => str_starts_with($route, '/allseeingeye/hk'),
        ));
    }

    /**
     * @param  list<string>  $routes
     * @return list<string>
     */
    private function uniqueSorted(array $routes): array
    {
        $routes = array_values(array_unique($routes));
        sort($routes);

        return $routes;
    }
}
