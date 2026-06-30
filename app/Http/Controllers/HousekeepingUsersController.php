<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingBanView;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingUsersController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function search(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'user/search');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $context = [
            'pageName' => 'Search Users',
            'players' => [],
        ];

        if ($request->isMethod('post')) {
            foreach (['searchField', 'searchQuery', 'searchType'] as $field) {
                if ((string) $request->input($field, '') === '') {
                    $this->alert($request, 'You need to enter all fields', 'danger');

                    return $this->render($template, 'housekeeping/users_search', $staff, $context);
                }
            }

            $field = (string) $request->input('searchField');
            $query = (string) $request->input('searchQuery');
            $type = (string) $request->input('searchType');

            if (in_array($field, ['username', 'id', 'credits', 'pixels', 'mission'], true)) {
                $context['players'] = $field === 'mission'
                    ? []
                    : $this->searchPlayers($field, $query, $type);
            }
        }

        return $this->render($template, 'housekeeping/users_search', $staff, $context);
    }

    public function create(Request $request, LegacyTemplate $template, LegacyPasswordHasher $hasher): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'user/create');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $context = [
            'pageName' => 'Create User',
            'defaultFigure' => '',
            'defaultMission' => '',
        ];

        if ($request->isMethod('post')) {
            foreach (['username', 'password', 'confirmpassword', 'figure', 'email', 'mission'] as $field) {
                if ((string) $request->input($field, '') === '') {
                    $this->alert($request, 'You need to enter all fields', 'danger');

                    return $this->render($template, 'housekeeping/users_create', $staff, $context);
                }
            }

            $password = (string) $request->input('password');
            $email = (string) $request->input('email');

            if ($password !== (string) $request->input('confirmpassword')) {
                $this->alert($request, 'The two passwords do not match', 'warning');
            } elseif (strlen($password) < 6) {
                $this->alert($request, 'The password needs to be at least 6 or more characters', 'warning');
            } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->alert($request, 'The email entered is not valid', 'warning');
            } else {
                $user = new User;
                $user->forceFill([
                    'username' => (string) $request->input('username'),
                    'password' => $hasher->make($password),
                    'figure' => (string) $request->input('figure'),
                    'pool_figure' => '',
                    'sex' => 'M',
                    'email' => $email,
                    'motto' => (string) $request->input('mission'),
                    'sso_ticket' => '',
                ])->save();

                if (Schema::hasTable('users_statistics')) {
                    DB::table('users_statistics')->insertOrIgnore(['user_id' => (int) $user->id]);
                }

                $this->alert($request, 'The new user has been successfully created. <a href="/'.trim((string) config('havana.housekeeping_path'), '/').'/users/edit?id='.(int) $user->id.'">Click here</a> to edit them.', 'success');
            }
        }

        return $this->render($template, 'housekeeping/users_create', $staff, $context);
    }

    public function edit(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'user/edit');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $playerId = $this->integerValue($request->input('id', $request->query('id'))) ?? 0;
        $player = $playerId > 0 ? User::query()->find($playerId) : null;

        if ($player === null) {
            $this->alert($request, $playerId > 0 ? 'The user does not exist' : 'You did not select a user to edit', 'danger');

            return $this->render($template, 'housekeeping/users_edit', $staff, ['pageName' => 'Edit User']);
        }

        if ((int) $staff->rank <= (int) $player->rank) {
            $this->alert($request, 'You cannot edit someone that has a equal or higher rank than you', 'danger');
        } elseif ($request->isMethod('post')) {
            $this->updatePlayer($request, $player);
        }

        return $this->render($template, 'housekeeping/users_edit', $staff, array_merge(
            ['pageName' => 'Edit User'],
            $this->editContext($player->fresh())
        ));
    }

    public function imitate(Request $request, string $username): RedirectResponse
    {
        $staff = $this->requirePermission($request, 'user/imitate');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $player = User::query()->where('username', $username)->first();

        if ($player !== null) {
            Auth::login($player);
            $request->session()->put('authenticated', true);
            $request->session()->put('captcha.invalid', false);
            $request->session()->put(self::USER_ID_KEY, (int) $player->id);
            $request->session()->put('clientAuthenticate', false);
            $request->session()->put(self::SESSION_KEY, false);
            $request->session()->put('lastRequest', (string) (time() + 900));
        }

        return redirect('/me');
    }

    public function bans(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request, 'bans');

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $page = max(0, (int) $request->query('page', 0));
        $sortBy = in_array($request->query('sort'), ['banned_at', 'banned_until'], true)
            ? (string) $request->query('sort')
            : 'banned_at';

        return $this->render($template, 'housekeeping/users_bans', $staff, [
            'pageName' => 'Bans',
            'bans' => $this->bansPage($page, $sortBy),
            'nextBans' => $this->bansPage($page + 1, $sortBy),
            'previousBans' => $page > 0 ? $this->bansPage($page - 1, $sortBy) : [],
            'page' => $page,
            'sortBy' => $sortBy,
        ]);
    }

    public function ban(Request $request): Response
    {
        $staff = $this->requirePermission($request, 'bans');

        if ($staff instanceof RedirectResponse) {
            return response('');
        }

        $player = User::query()->where('username', (string) $request->query('username', ''))->first();

        if ($player === null) {
            return response("User doesn't exist");
        }

        $message = 'Banned for breaking the HabboWay';
        $bannedUntil = now()->addSeconds(999999999);

        DB::table('users_bans')->insert([
            'ban_type' => 'USER_ID',
            'banned_value' => (string) $player->id,
            'message' => $message,
            'banned_until' => $bannedUntil,
            'banned_at' => now(),
            'banned_by' => (int) $staff->id,
            'is_active' => true,
        ]);

        if ((string) $player->machine_id !== '') {
            DB::table('users_bans')->insert([
                'ban_type' => 'MACHINE_ID',
                'banned_value' => (string) $player->machine_id,
                'message' => $message,
                'banned_until' => $bannedUntil,
                'banned_at' => now(),
                'banned_by' => (int) $staff->id,
                'is_active' => true,
            ]);
        }

        return response('User has been banned');
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
    private function render(LegacyTemplate $template, string $view, User $staff, array $context = []): Response
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
        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/'));
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    /** @return list<HousekeepingPlayerView> */
    private function searchPlayers(string $column, string $input, string $type): array
    {
        $query = User::query();

        match ($type) {
            'starts_with' => $query->where($column, 'like', $input.'%'),
            'ends_with' => $query->where($column, 'like', '%'.$input),
            'equals' => $query->where($column, $input),
            default => $query->where($column, 'like', '%'.$input.'%'),
        };

        return $query
            ->orderBy('username')
            ->limit(50)
            ->get()
            ->map(fn (User $user): HousekeepingPlayerView => new HousekeepingPlayerView($user))
            ->all();
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

    private function updatePlayer(Request $request, User $player): void
    {
        foreach (['username', 'figure', 'email', 'motto', 'credits', 'pixels'] as $field) {
            if ((string) $request->input($field, '') === '') {
                $this->alert($request, 'You need to enter all fields. The '.$field.' field is missing.', 'danger');

                return;
            }
        }

        if (! filter_var((string) $request->input('email'), FILTER_VALIDATE_EMAIL)) {
            $this->alert($request, 'The email entered is not valid', 'warning');
        }

        if (! is_numeric($request->input('credits'))) {
            $this->alert($request, 'The value supplied for credits is not a number', 'warning');

            return;
        }

        if (! is_numeric($request->input('pixels'))) {
            $this->alert($request, 'The value supplied for pixels is not a number', 'warning');

            return;
        }

        $player->forceFill([
            'figure' => (string) $request->input('figure'),
            'motto' => (string) $request->input('motto'),
            'pixels' => (int) $request->input('pixels'),
            'credits' => (int) $request->input('credits'),
            'email' => (string) $request->input('email'),
        ])->save();

        $this->alert($request, 'The user has been successfully saved', 'success');
    }

    /** @return array<string, mixed> */
    private function editContext(?User $player): array
    {
        return [
            'playerId' => (int) ($player?->id ?? 0),
            'playerUsername' => (string) ($player?->username ?? ''),
            'playerEmail' => (string) ($player?->email ?? ''),
            'playerMotto' => (string) ($player?->motto ?? ''),
            'playerPixels' => (int) ($player?->pixels ?? 0),
            'playerCredits' => (int) ($player?->credits ?? 0),
            'playerFigure' => (string) ($player?->figure ?? ''),
        ];
    }

    /** @return list<HousekeepingBanView> */
    private function bansPage(int $page, string $sortBy): array
    {
        if (! Schema::hasTable('users_bans')) {
            return [];
        }

        /** @var Builder $query */
        $query = DB::table('users_bans')
            ->where('is_active', true)
            ->orderByDesc($sortBy)
            ->offset(max(0, $page) * 25)
            ->limit(25);

        return $query
            ->get()
            ->map(fn (object $row): HousekeepingBanView => new HousekeepingBanView($row))
            ->all();
    }
}
