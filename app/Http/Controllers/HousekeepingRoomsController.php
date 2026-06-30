<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use App\Support\HousekeepingRoomBanView;
use App\Support\HousekeepingRoomCategoryView;
use App\Support\HousekeepingRoomEventView;
use App\Support\HousekeepingRoomModelView;
use App\Support\HousekeepingRoomRightView;
use App\Support\HousekeepingRoomView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingRoomsController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function rooms(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $query = trim((string) $request->query('query', ''));

        return $this->render($template, 'housekeeping/rooms', $staff, [
            'pageName' => 'Rooms',
            'query' => $query,
            'rooms' => $this->roomsList($query),
        ]);
    }

    public function edit(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id === null || $id <= 0) {
            $this->alert($request, 'Room ID is required', 'danger');

            return redirect($this->housekeepingUrl('/rooms'));
        }

        $room = $this->room($id);

        if ($room === null) {
            $this->alert($request, 'Room does not exist', 'danger');

            return redirect($this->housekeepingUrl('/rooms'));
        }

        if ($request->isMethod('post')) {
            $name = trim((string) $request->input('name', ''));
            $model = trim((string) $request->input('model', ''));
            $ownerId = (int) $request->input('owner_id', 0);
            $categoryId = (int) $request->input('category', 0);
            $accessType = (int) $request->input('accesstype', 0);
            $visitorsMax = (int) $request->input('visitors_max', 0);

            if ($name === '') {
                $this->alert($request, 'Room name cannot be blank', 'danger');
            } elseif ($model === '' || ! DB::table('rooms_models')->where('model_id', $model)->exists()) {
                $this->alert($request, 'Room model must match an existing model ID', 'danger');
            } elseif ($ownerId > 0 && ! User::query()->whereKey($ownerId)->exists()) {
                $this->alert($request, 'Owner ID must be 0 for public rooms or an existing user ID', 'danger');
            } elseif (! DB::table('rooms_categories')->where('id', $categoryId)->exists()) {
                $this->alert($request, 'Room category must exist', 'danger');
            } elseif ($accessType < 0 || $accessType > 2) {
                $this->alert($request, 'Room access type must be open, closed, or password', 'danger');
            } elseif ($visitorsMax < 1) {
                $this->alert($request, 'Visitor limit must be at least 1', 'danger');
            } else {
                $this->saveRoom($id, $ownerId, $categoryId, $name, $model, $accessType, $visitorsMax, $request);
                $this->alert($request, 'Room saved successfully', 'success');

                return redirect($this->housekeepingUrl('/rooms/edit?id='.$id));
            }
        }

        return $this->render($template, 'housekeeping/room_edit', $staff, [
            'pageName' => 'Edit Room',
            'room' => $room,
            'categories' => $this->roomCategoriesList(),
            'models' => $this->roomModelsList(),
            'rights' => $this->roomRights($id),
            'bans' => $this->roomBans($id),
            'events' => $this->roomEvents($id),
            'entryBadges' => $this->roomEntryBadges($id),
        ]);
    }

    public function hide(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null && $id > 0) {
            $hidden = filter_var($request->query('hidden', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            DB::table('rooms')->where('id', $id)->update(['is_hidden' => $hidden]);
            $this->alert($request, $hidden ? 'Room hidden successfully' : 'Room unhidden successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/rooms'));
    }

    public function staffPick(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null && $id > 0) {
            if (! DB::table('rooms')->where('id', $id)->exists()) {
                $this->alert($request, 'Room does not exist', 'danger');
            } else {
                $enabled = filter_var($request->query('enabled', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
                DB::table('cms_recommended')
                    ->where('recommended_id', $id)
                    ->where('type', 'ROOM')
                    ->where('is_staff_pick', true)
                    ->delete();

                if ($enabled) {
                    DB::table('cms_recommended')->insert([
                        'recommended_id' => $id,
                        'type' => 'ROOM',
                        'is_staff_pick' => true,
                    ]);
                }
                $this->alert($request, $enabled ? 'Room added to staff picks' : 'Room removed from staff picks', 'success');
            }
        }

        return redirect($this->housekeepingUrl('/rooms'));
    }

    public function delete(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null && $id > 0 && DB::table('rooms')->where('id', $id)->exists()) {
            DB::table('rooms')->where('id', $id)->delete();
            DB::table('cms_recommended')->where('recommended_id', $id)->where('type', 'ROOM')->delete();
            $this->alert($request, 'Room deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/rooms'));
    }

    private function requirePermission(Request $request): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, 'rooms/manage')) {
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

    private function integerQuery(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /** @return list<HousekeepingRoomView> */
    private function roomsList(string $query): array
    {
        $rooms = DB::table('rooms')
            ->leftJoin('users', 'rooms.owner_id', '=', 'users.id')
            ->select('rooms.*', 'users.username')
            ->selectRaw("exists(select 1 from cms_recommended where cms_recommended.recommended_id = rooms.id and cms_recommended.type = 'ROOM' and cms_recommended.is_staff_pick = 1) as staff_pick")
            ->orderByDesc('rooms.id')
            ->limit(100);

        if ($query !== '') {
            $normalised = mb_strtolower($query);
            $roomId = filter_var($normalised, FILTER_VALIDATE_INT);
            $rooms->where(function ($builder) use ($normalised, $roomId): void {
                if ($roomId !== false) {
                    $builder->where('rooms.id', (int) $roomId);
                }

                $builder->orWhereRaw('lower(rooms.name) like ?', ['%'.$normalised.'%'])
                    ->orWhereRaw('lower(users.username) like ?', ['%'.$normalised.'%']);
            });
        }

        return $rooms->get()
            ->map(fn (object $row): HousekeepingRoomView => new HousekeepingRoomView($row))
            ->all();
    }

    private function room(int $id): ?HousekeepingRoomView
    {
        $row = DB::table('rooms')
            ->leftJoin('users', 'rooms.owner_id', '=', 'users.id')
            ->select('rooms.*', 'users.username')
            ->selectRaw("exists(select 1 from cms_recommended where cms_recommended.recommended_id = rooms.id and cms_recommended.type = 'ROOM' and cms_recommended.is_staff_pick = 1) as staff_pick")
            ->where('rooms.id', $id)
            ->first();

        return $row !== null ? new HousekeepingRoomView($row) : null;
    }

    private function saveRoom(int $id, int $ownerId, int $categoryId, string $name, string $model, int $accessType, int $visitorsMax, Request $request): void
    {
        $payload = [
            'owner_id' => (string) $ownerId,
            'category' => $categoryId,
            'name' => $name,
            'description' => trim((string) $request->input('description', '')),
            'model' => $model,
            'visitors_max' => $visitorsMax,
            'rating' => (int) $request->input('rating', 0),
            'group_id' => (int) $request->input('group_id', 0),
            'is_hidden' => $request->boolean('is_hidden'),
        ];

        foreach ([
            'ccts' => trim((string) $request->input('ccts', '')),
            'wallpaper' => (int) $request->input('wallpaper', 0),
            'floor' => (int) $request->input('floor', 0),
            'landscape' => trim((string) $request->input('landscape', '0')),
            'showname' => $request->boolean('showname'),
            'superusers' => $request->boolean('superusers'),
            'accesstype' => $accessType,
            'password' => (string) $request->input('password', ''),
            'icon_data' => trim((string) $request->input('icon_data', '0|0|')),
        ] as $column => $value) {
            if (Schema::hasColumn('rooms', $column)) {
                $payload[$column] = $value;
            }
        }

        DB::table('rooms')->where('id', $id)->update($payload);
    }

    /** @return list<HousekeepingRoomCategoryView> */
    private function roomCategoriesList(): array
    {
        return DB::table('rooms_categories')
            ->orderBy('parent_id')
            ->orderBy('order_id')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingRoomCategoryView => new HousekeepingRoomCategoryView($row))
            ->all();
    }

    /** @return list<HousekeepingRoomModelView> */
    private function roomModelsList(): array
    {
        return DB::table('rooms_models')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): HousekeepingRoomModelView => new HousekeepingRoomModelView($row))
            ->all();
    }

    /** @return list<HousekeepingRoomRightView> */
    private function roomRights(int $roomId): array
    {
        if (! Schema::hasTable('rooms_rights')) {
            return [];
        }

        return DB::table('rooms_rights')
            ->leftJoin('users', 'rooms_rights.user_id', '=', 'users.id')
            ->where('rooms_rights.room_id', $roomId)
            ->orderBy('users.username')
            ->get(['rooms_rights.user_id', 'users.username'])
            ->map(fn (object $row): HousekeepingRoomRightView => new HousekeepingRoomRightView($row))
            ->all();
    }

    /** @return list<HousekeepingRoomBanView> */
    private function roomBans(int $roomId): array
    {
        if (! Schema::hasTable('rooms_bans')) {
            return [];
        }

        return DB::table('rooms_bans')
            ->leftJoin('users', 'rooms_bans.user_id', '=', 'users.id')
            ->where('rooms_bans.room_id', $roomId)
            ->orderByDesc('rooms_bans.expire_at')
            ->get(['rooms_bans.user_id', 'rooms_bans.expire_at', 'users.username'])
            ->map(fn (object $row): HousekeepingRoomBanView => new HousekeepingRoomBanView($row))
            ->all();
    }

    /** @return list<HousekeepingRoomEventView> */
    private function roomEvents(int $roomId): array
    {
        return DB::table('rooms_events')
            ->leftJoin('users', 'rooms_events.user_id', '=', 'users.id')
            ->where('rooms_events.room_id', $roomId)
            ->orderByDesc('rooms_events.expire_time')
            ->get(['rooms_events.*', 'users.username'])
            ->map(fn (object $row): HousekeepingRoomEventView => new HousekeepingRoomEventView($row))
            ->all();
    }

    /** @return list<string> */
    private function roomEntryBadges(int $roomId): array
    {
        return DB::table('rooms_entry_badges')
            ->where('room_id', $roomId)
            ->orderBy('badge')
            ->pluck('badge')
            ->map(fn (mixed $badge): string => (string) $badge)
            ->all();
    }
}
