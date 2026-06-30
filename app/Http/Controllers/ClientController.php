<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\HotelStatus;
use App\Services\LegacyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function client(Request $request): RedirectResponse
    {
        $this->clearXssKey($request);

        if (! $this->currentUser($request)) {
            return redirect('/login_popup');
        }

        $query = $request->getQueryString();

        return redirect('/shockwave_client'.($query ? '?'.$query : ''));
    }

    public function shockwave(Request $request, HavanaConfig $config, LegacyTemplate $template): RedirectResponse|Response
    {
        return $this->renderClient($request, $config, $template, 'client');
    }

    public function flash(Request $request, HavanaConfig $config, LegacyTemplate $template): RedirectResponse|Response
    {
        return $this->renderClient($request, $config, $template, 'client_flash');
    }

    public function installShockwave(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/login_popup');
        }

        if ($this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        return response($template->render('client_install_shockwave', [
            'playerDetails' => $user,
        ]));
    }

    public function updateHabboCount(HotelStatus $hotelStatus): Response
    {
        $status = $hotelStatus->snapshot();

        return response('', 200, [
            'X-JSON' => json_encode([
                'habboCountText' => $status['formattedUsersOnline'].' members online',
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    public function blank(): Response
    {
        return response('');
    }

    public function clientError(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if ($user && $this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        return response($template->render('client_error', [
            'playerDetails' => $user,
            'errorId' => (string) $request->query('error_id', ''),
        ]));
    }

    public function clientConnectionFailed(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if ($user && $this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        return response($template->render('client_connection_failed', [
            'playerDetails' => $user,
            'errorId' => (string) $request->query('error_id', ''),
        ]));
    }

    private function renderClient(
        Request $request,
        HavanaConfig $config,
        LegacyTemplate $template,
        string $view,
    ): RedirectResponse|Response {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/login_popup');
        }

        if ($this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        $request->session()->put('clientRequest', $request->getRequestUri());

        if ($request->session()->get('clientAuthenticate')) {
            return redirect('/account/reauthenticate');
        }

        $forwardType = -1;
        $forwardId = -1;
        $forwardRoom = false;

        if ($request->query->has('forwardId')) {
            $forwardRoom = true;

            if (
                preg_match('/^-?\d+$/', (string) $request->query('forwardId', '')) === 1
                && preg_match('/^-?\d+$/', (string) $request->query('roomId', '')) === 1
            ) {
                $forwardType = (int) $request->query('forwardId');
                $forwardId = (int) $request->query('roomId');
            }
        }

        if ($request->query->has('createRoom') && preg_match('/^\d+$/', (string) $request->query('createRoom', '')) === 1) {
            $user = $this->handleCreateRoom($request, $user);
            $forwardRoom = true;
            $forwardType = 2;
            $forwardId = (int) $user->selected_room_id;
        }

        $shortcut = '';

        if ((string) $request->query('shortcut', '') === 'roomomatic') {
            $shortcut = 'shortcut.id=1;';
        }

        $ssoTicket = $this->ssoTicket($user, $config);
        $forwardValue = $forwardRoom ? 'forward.type='.$forwardType.';forward.id='.$forwardId.';processlog.url=' : '';

        return response($template->render($view, [
            'playerDetails' => $user->fresh(),
            'ssoTicket' => $ssoTicket,
            'forwardRoom' => $forwardRoom,
            'forwardType' => $forwardType,
            'forwardId' => $forwardId,
            'forward' => $forwardRoom ? '<param name="sw9" value="'.$forwardValue.'">' : '',
            'forwardSub' => $forwardRoom ? 'sw9="'.$forwardValue.'"' : '',
            'forwardScript' => $forwardRoom ? '<param name=\"sw9\" value=\"'.$forwardValue.'\">' : '',
            'forwardSubScript' => $forwardRoom ? 'sw9=\"'.$forwardValue.'\"' : '',
            'shortcut' => $shortcut,
            'preferredCountry' => 'us',
        ]));
    }

    private function handleCreateRoom(Request $request, User $user): User
    {
        $createRoom = (string) $request->query('createRoom', '');

        if (preg_match('/^\d+$/', $createRoom) !== 1) {
            return $user;
        }

        $roomType = (int) $createRoom;

        if ($roomType < 0 || $roomType > 5) {
            return $user;
        }

        $statistics = DB::table('users_statistics')->where('user_id', (int) $user->id)->first();

        if ((int) $user->selected_room_id !== 0 && (! $statistics || (int) $statistics->newbie_room_layout !== 0)) {
            return $user;
        }

        $roomId = (int) $user->selected_room_id;
        $createdRoom = false;

        if ((int) $user->selected_room_id === 0) {
            $roomId = $this->createStarterRoom((int) $user->id, (string) $user->username, $roomType);

            if ($roomId <= 0) {
                return $user;
            }

            $createdRoom = true;
            $user->forceFill(['selected_room_id' => $roomId])->save();
        }

        if ($createdRoom || (! $statistics || (int) $statistics->newbie_room_layout === 0)) {
            DB::table('users_statistics')->updateOrInsert(
                ['user_id' => (int) $user->id],
                [
                    'newbie_room_layout' => $roomType + 1,
                    'newbie_gift' => 1,
                    'newbie_gift_time' => time() + 86400,
                ],
            );
        }

        return User::query()->find((int) $user->id) ?? $user;
    }

    private function hasActiveUserBan(User $user): bool
    {
        return DB::table('users_bans')
            ->where('ban_type', 'USER_ID')
            ->where('banned_value', (string) $user->id)
            ->where('is_active', true)
            ->where('banned_until', '>', now())
            ->exists();
    }

    private function createStarterRoom(int $userId, string $username, int $roomType): int
    {
        $layout = $this->starterRoomLayout($roomType);
        $definitions = DB::table('items_definitions')
            ->whereIn('sprite', [
                'noob_stool*'.($roomType + 1),
                'noob_table*'.($roomType + 1),
                'noob_window_double',
            ])
            ->pluck('id', 'sprite');

        if (! $definitions->has('noob_stool*'.($roomType + 1)) || ! $definitions->has('noob_table*'.($roomType + 1)) || ! $definitions->has('noob_window_double')) {
            return 0;
        }

        return DB::transaction(function () use ($userId, $username, $roomType, $layout, $definitions): int {
            $roomId = (int) DB::table('rooms')->insertGetId([
                'owner_id' => (string) $userId,
                'name' => $username."'s Room",
                'description' => $username.' has entered the building',
                'model' => 'model_s',
                'showname' => true,
                'password' => '',
                'accesstype' => 0,
                'wallpaper' => $layout['wallpaper'],
                'floor' => $layout['floor'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('items')->insert([
                [
                    'order_id' => -1,
                    'user_id' => $userId,
                    'room_id' => $roomId,
                    'definition_id' => (int) $definitions['noob_stool*'.($roomType + 1)],
                    'x' => $layout['stool_x'],
                    'y' => $layout['stool_y'],
                    'z' => '0',
                    'wall_position' => '',
                    'rotation' => $layout['stool_rotation'],
                    'custom_data' => '',
                    'is_hidden' => false,
                    'is_trading' => false,
                    'expire_time' => -1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'order_id' => -1,
                    'user_id' => $userId,
                    'room_id' => 0,
                    'definition_id' => (int) $definitions['noob_table*'.($roomType + 1)],
                    'x' => 0,
                    'y' => 0,
                    'z' => '0',
                    'wall_position' => '',
                    'rotation' => 0,
                    'custom_data' => '',
                    'is_hidden' => false,
                    'is_trading' => false,
                    'expire_time' => -1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'order_id' => -1,
                    'user_id' => $userId,
                    'room_id' => $roomId,
                    'definition_id' => (int) $definitions['noob_window_double'],
                    'x' => 0,
                    'y' => 0,
                    'z' => '0',
                    'wall_position' => ':w=3,0 l=13,71 r',
                    'rotation' => 0,
                    'custom_data' => '',
                    'is_hidden' => false,
                    'is_trading' => false,
                    'expire_time' => -1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            return $roomId;
        });
    }

    /**
     * @return array{stool_x: int, stool_y: int, stool_rotation: int, floor: int, wallpaper: int}
     */
    private function starterRoomLayout(int $roomType): array
    {
        return match ($roomType) {
            1 => ['stool_x' => 3, 'stool_y' => 6, 'stool_rotation' => 4, 'floor' => 0, 'wallpaper' => 607],
            2 => ['stool_x' => 2, 'stool_y' => 2, 'stool_rotation' => 4, 'floor' => 301, 'wallpaper' => 1901],
            3 => ['stool_x' => 1, 'stool_y' => 2, 'stool_rotation' => 2, 'floor' => 110, 'wallpaper' => 1801],
            4 => ['stool_x' => 3, 'stool_y' => 6, 'stool_rotation' => 0, 'floor' => 104, 'wallpaper' => 503],
            5 => ['stool_x' => 3, 'stool_y' => 6, 'stool_rotation' => 0, 'floor' => 107, 'wallpaper' => 804],
            default => ['stool_x' => 1, 'stool_y' => 6, 'stool_rotation' => 2, 'floor' => 601, 'wallpaper' => 1501],
        };
    }

    private function currentUser(Request $request): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId > 0 && $request->session()->get('authenticated')) {
            return User::query()->find($userId);
        }

        return null;
    }

    private function clearXssKey(Request $request): void
    {
        $request->session()->forget(['xssKey', 'xssSeed', 'xssRequested']);
    }

    private function ssoTicket(User $user, HavanaConfig $config): string
    {
        $ticket = (string) $user->sso_ticket;

        if ($config->boolean('reset.sso.after.login') || $ticket === '') {
            $ticket = (string) Str::uuid();
            $user->forceFill(['sso_ticket' => $ticket])->save();
        }

        return $ticket;
    }
}
