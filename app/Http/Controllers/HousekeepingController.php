<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function dashboard(Request $request, LegacyTemplate $template): Response
    {
        $currentUser = $this->currentHousekeepingUser($request);

        if ($currentUser === null) {
            return response($template->render('housekeeping/login', [
                'pageName' => 'Login',
            ]));
        }

        $page = max(0, (int) $request->query('page', 0));
        $zeroCoinsFlag = $request->query->has('zerocoins');
        $sortBy = in_array($request->query('sort'), ['last_online', 'created_at'], true)
            ? (string) $request->query('sort')
            : 'created_at';

        $html = $template->render('housekeeping/dashboard', [
            'housekeepingManager' => new HousekeepingManagerView,
            'pageName' => 'Dashboard',
            'playerDetails' => new HousekeepingPlayerView($currentUser),
            'players' => $this->players($page, $zeroCoinsFlag, $sortBy),
            'nextPlayers' => $this->players($page + 1, $zeroCoinsFlag, $sortBy),
            'previousPlayers' => $page > 0 ? $this->players($page - 1, $zeroCoinsFlag, $sortBy) : [],
            'page' => $page,
            'sortBy' => $sortBy,
            'stats' => $this->stats(),
            'zeroCoinsFlag' => $zeroCoinsFlag,
        ]);

        $request->session()->forget('alertMessage');

        return response($html);
    }

    public function login(Request $request, LegacyTemplate $template, LegacyPasswordHasher $hasher): RedirectResponse|Response
    {
        if (! $request->isMethod('post')) {
            return $this->dashboard($request, $template);
        }

        $username = (string) $request->input('hkusername', '');
        $password = (string) $request->input('hkpassword', '');

        if ($username === '' || $password === '') {
            $this->alert($request, 'You need to enter both your email and password', 'danger');

            return $this->redirectToHousekeeping();
        }

        $user = User::query()->where('username', $username)->first();

        if ($user === null || ! $hasher->check($password, (string) $user->password)) {
            $this->alert($request, 'You have entered invalid details', 'danger');

            return $this->redirectToHousekeeping();
        }

        if (! (new HousekeepingManagerView)->hasPermission((int) $user->rank, 'root/login')) {
            $this->alert($request, "You don't have permission", 'warning');

            return $this->redirectToHousekeeping();
        }

        $request->session()->put(self::SESSION_KEY, true);
        $request->session()->put(self::USER_ID_KEY, (int) $user->id);

        return $this->redirectToHousekeeping();
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->session()->get(self::SESSION_KEY, false)) {
            $this->alert($request, 'Successfully logged out!', 'success');
        }

        $request->session()->put(self::SESSION_KEY, false);

        return $this->redirectToHousekeeping();
    }

    private function currentHousekeepingUser(Request $request): ?User
    {
        if (! $request->session()->get(self::SESSION_KEY, false)) {
            return null;
        }

        $userId = (int) $request->session()->get(self::USER_ID_KEY, 0);

        return $userId > 0 ? User::query()->find($userId) : null;
    }

    /** @return list<HousekeepingPlayerView> */
    private function players(int $page, bool $zeroCoinsFlag, string $sortBy): array
    {
        $query = User::query();

        if ($zeroCoinsFlag) {
            $query->where('credits', '<=', 0);
        }

        return $query
            ->orderByDesc($sortBy)
            ->offset(max(0, $page) * 20)
            ->limit(20)
            ->get()
            ->map(fn (User $user): HousekeepingPlayerView => new HousekeepingPlayerView($user))
            ->all();
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        return [
            'userCount' => $this->countTable('users'),
            'inventoryItemsCount' => $this->countTable('items'),
            'roomItemCount' => $this->countTable('rooms_items'),
            'groupCount' => $this->countTable('groups'),
            'petCount' => $this->countTable('pets'),
            'photoCount' => $this->countTable('camera_photos'),
        ];
    }

    private function countTable(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertColour', $colour);
        $request->session()->put('alertMessage', $message);
    }

    private function redirectToHousekeeping(): RedirectResponse
    {
        return redirect('/'.trim((string) config('havana.housekeeping_path'), '/'));
    }
}
