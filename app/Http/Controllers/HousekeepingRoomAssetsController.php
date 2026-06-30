<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use App\Support\HousekeepingRoomAdView;
use App\Support\HousekeepingRoomBadgeMap;
use App\Support\HousekeepingUtilView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HousekeepingRoomAssetsController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function roomAds(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_ads');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            foreach ($request->input() as $key => $value) {
                if (! str_starts_with((string) $key, 'roomad-id-')) {
                    continue;
                }

                $id = $this->integerValue($value);
                if ($id === null) {
                    continue;
                }

                $roomId = $this->integerInput($request, 'roomad-'.$id.'-roomid');

                if ($roomId === null) {
                    continue;
                }

                DB::table('rooms_ads')->where('id', $id)->update([
                    'room_id' => $roomId,
                    'image' => $this->nullableString($request->input('roomad-'.$id.'-image')),
                    'url' => $this->nullableString($request->input('roomad-'.$id.'-url')),
                    'enabled' => $request->has('roomad-'.$id.'-enabled'),
                    'is_loading_ad' => $request->has('roomad-'.$id.'-loading-ad'),
                ]);
            }

            $this->alert($request, 'All room ads have been saved successfully!', 'success');
        }

        return $this->render($template, 'housekeeping/room_ads', $staff, [
            'pageName' => 'Room Ads',
            'roomAds' => $this->roomAdsList(),
        ]);
    }

    public function createRoomAd(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_ads');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            $roomId = $this->integerInput($request, 'roomid');

            if ($roomId === null) {
                $this->alert($request, 'Error occurred, make sure the room ID is a valid number', 'danger');
            } else {
                DB::table('rooms_ads')->insert([
                    'room_id' => $roomId,
                    'url' => $this->nullableString($request->input('url')),
                    'image' => $this->nullableString($request->input('image')),
                    'enabled' => $request->has('enabled'),
                    'is_loading_ad' => $request->has('loading-ad'),
                ]);

                $this->alert($request, 'Room ad has been created successfully', 'success');
            }
        }

        return $this->render($template, 'housekeeping/room_ads_create', $staff, [
            'pageName' => 'Room Ads',
        ]);
    }

    public function deleteRoomAd(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_ads');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null) {
            DB::table('rooms_ads')->where('id', $id)->delete();
            $this->alert($request, 'Room ad has been deleted successfully', 'danger');
        }

        return $this->render($template, 'housekeeping/room_ads', $staff, [
            'pageName' => 'Room Ads',
            'roomAds' => $this->roomAdsList(),
        ]);
    }

    public function roomBadges(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_badges');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            $badges = [];

            foreach ($request->input() as $key => $value) {
                if (! str_starts_with((string) $key, 'roombadge-id-')) {
                    continue;
                }

                $id = (string) $value;
                $roomId = $this->integerInput($request, 'roomad-'.$id.'-roomid');
                $badge = (string) $request->input('roomad-'.$id.'-badge', '');

                if ($roomId === null) {
                    $badges = null;
                    break;
                }

                $badges[$roomId][] = $badge;
            }

            if ($badges === null) {
                $this->alert($request, 'Error occurred, make sure the room ID is a valid number', 'danger');
            } else {
                DB::table('rooms_entry_badges')->delete();

                foreach ($badges as $roomId => $roomBadges) {
                    foreach ($roomBadges as $badge) {
                        DB::table('rooms_entry_badges')->insert([
                            'room_id' => $roomId,
                            'badge' => $badge,
                        ]);
                    }
                }

                $this->alert($request, 'All badge rooms have been saved successfully!', 'success');
            }
        }

        return $this->renderRoomBadges($template, $staff);
    }

    public function createRoomBadge(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_badges');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        if ($request->isMethod('post')) {
            $roomId = $this->integerInput($request, 'roomid');

            if ($roomId === null) {
                $this->alert($request, 'Error occurred, make sure the room ID is a valid number', 'danger');
            } else {
                DB::table('rooms_entry_badges')->insert([
                    'room_id' => $roomId,
                    'badge' => trim((string) $request->input('badgecode', '')),
                ]);

                $this->alert($request, 'Successfully created the room entry badge', 'success');

                return redirect($this->housekeepingUrl('/room_badges'));
            }
        }

        return $this->render($template, 'housekeeping/room_badges_create', $staff, [
            'pageName' => 'Room Badges',
        ]);
    }

    public function deleteRoomBadge(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'room_badges');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->rawQueryValue($request, 'id') ?? '';

        if ($id === '' || ! str_contains($id, '_')) {
            $this->alert($request, 'There was no badge selected to delete', 'danger');
        } else {
            [$roomId, $badge] = explode('_', $id, 2);

            DB::table('rooms_entry_badges')
                ->where('room_id', (int) $roomId)
                ->where('badge', $badge)
                ->delete();

            $this->alert($request, 'Successfully deleted the badge', 'success');
        }

        return $this->renderRoomBadges($template, $staff);
    }

    private function requirePermission(Request $request, string $permission): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, $permission)) {
            return $this->redirectToHousekeeping();
        }

        return $user;
    }

    private function currentHousekeepingUser(Request $request): ?User
    {
        if (! $request->session()->get(self::SESSION_KEY, false)) {
            return null;
        }

        $userId = (int) $request->session()->get(self::USER_ID_KEY, 0);

        return $userId > 0 ? User::query()->find($userId) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function render(LegacyTemplate $template, string $view, User $staff, array $context): Response
    {
        $html = $template->render($view, array_merge([
            'housekeepingManager' => new HousekeepingManagerView,
            'playerDetails' => new HousekeepingPlayerView($staff),
        ], $context));

        request()->session()->forget('alertMessage');

        return response($html);
    }

    private function renderRoomBadges(LegacyTemplate $template, User $staff): Response
    {
        return $this->render($template, 'housekeeping/room_badges', $staff, [
            'pageName' => 'Room Badges',
            'roomBadges' => new HousekeepingRoomBadgeMap($this->roomEntryBadges()),
            'util' => new HousekeepingUtilView,
        ]);
    }

    private function redirectToHousekeeping(): RedirectResponse
    {
        return redirect($this->housekeepingUrl());
    }

    private function housekeepingUrl(string $suffix = ''): string
    {
        return '/'.trim((string) config('havana.housekeeping_path'), '/').$suffix;
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function integerInput(Request $request, string $key): ?int
    {
        return $this->integerValue($request->input($key));
    }

    private function integerQuery(Request $request, string $key): ?int
    {
        return $this->integerValue($request->query($key));
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function rawQueryValue(Request $request, string $key): ?string
    {
        $query = (string) $request->server('QUERY_STRING', '');

        if ($query === '') {
            return null;
        }

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if (urldecode($name) === $key) {
                return urldecode($value);
            }
        }

        return null;
    }

    /** @return list<HousekeepingRoomAdView> */
    private function roomAdsList(): array
    {
        return DB::table('rooms_ads')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingRoomAdView => new HousekeepingRoomAdView($row))
            ->all();
    }

    /** @return array<int, list<string>> */
    private function roomEntryBadges(): array
    {
        $badges = [];

        foreach (DB::table('rooms_entry_badges')->orderBy('room_id')->orderBy('badge')->get() as $row) {
            $badges[(int) $row->room_id][] = (string) $row->badge;
        }

        return $badges;
    }
}
