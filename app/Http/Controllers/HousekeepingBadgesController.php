<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingBadgeAssignmentView;
use App\Support\HousekeepingBadgeAuditView;
use App\Support\HousekeepingBadgeCatalogueView;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class HousekeepingBadgesController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function badges(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $query = trim((string) $request->query('query', ''));

        return $this->render($template, 'housekeeping/badges', $staff, [
            'pageName' => 'Badges',
            'query' => $query,
            'assignments' => $this->assignments($query),
            'catalogue' => $this->catalogue(),
            'audit' => $this->audit(),
        ]);
    }

    public function grant(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $userId = $this->integerInput($request, 'user_id');
        $badge = trim((string) $request->input('badge', ''));

        if ($userId === null || ! User::query()->whereKey($userId)->exists()) {
            $this->alert($request, 'Target user does not exist', 'danger');
        } elseif (! $this->validBadgeCode($badge)) {
            $this->alert($request, 'Badge code must be 1 to 50 letters, numbers, underscores, or hyphens', 'danger');
        } elseif ($this->hasBadge($userId, $badge)) {
            $this->alert($request, 'Target user already has that badge', 'warning');
        } else {
            DB::table('users_badges')->insert([
                'user_id' => $userId,
                'badge' => $badge,
            ]);
            $this->auditBadge('badge_grant', (int) $staff->id, $userId, $badge, 'Granted from housekeeping');
            $this->alert($request, 'Badge granted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/badges?query='.($userId ?? 0)));
    }

    public function update(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $userId = $this->integerInput($request, 'user_id');
        $badge = trim((string) $request->input('badge', ''));
        $slotId = $this->integerInput($request, 'slot_id');

        if ($userId === null || $slotId === null || ! $this->hasBadge($userId, $badge)) {
            $this->alert($request, 'Badge assignment does not exist', 'danger');
        } elseif ($slotId < 0 || $slotId > 5) {
            $this->alert($request, 'Badge slot must be between 0 and 5', 'danger');
        } else {
            DB::table('users_badges')
                ->where('user_id', $userId)
                ->where('badge', $badge)
                ->update([
                    'equipped' => $request->boolean('equipped'),
                    'slot_id' => $slotId,
                ]);
            $this->auditBadge('badge_update', (int) $staff->id, $userId, $badge, 'Updated equipped/slot from housekeeping');
            $this->alert($request, 'Badge assignment updated successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/badges?query='.($userId ?? 0)));
    }

    public function remove(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $userId = $this->integerQuery($request, 'user_id');
        $badge = trim((string) $request->query('badge', ''));

        if ($userId !== null && $this->hasBadge($userId, $badge)) {
            DB::table('users_badges')
                ->where('user_id', $userId)
                ->where('badge', $badge)
                ->delete();
            $this->auditBadge('badge_remove', (int) $staff->id, $userId, $badge, 'Removed from housekeeping');
            $this->alert($request, 'Badge removed successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/badges?query='.($userId ?? 0)));
    }

    private function requirePermission(Request $request): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, 'badges')) {
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

    /** @return list<HousekeepingBadgeAssignmentView> */
    private function assignments(string $query): array
    {
        $assignments = DB::table('users_badges')
            ->leftJoin('users', 'users_badges.user_id', '=', 'users.id')
            ->select('users_badges.*', 'users.username')
            ->orderByDesc('users_badges.user_id')
            ->orderBy('users_badges.badge')
            ->limit(100);

        if ($query !== '') {
            $normalised = mb_strtolower($query);
            $userId = filter_var($normalised, FILTER_VALIDATE_INT);
            $assignments->where(function ($builder) use ($normalised, $userId): void {
                if ($userId !== false) {
                    $builder->where('users_badges.user_id', (int) $userId);
                }

                $builder->orWhereRaw('lower(users.username) like ?', ['%'.$normalised.'%'])
                    ->orWhereRaw('lower(users_badges.badge) like ?', ['%'.$normalised.'%']);
            });
        }

        return $assignments->get()
            ->map(fn (object $row): HousekeepingBadgeAssignmentView => new HousekeepingBadgeAssignmentView($row))
            ->all();
    }

    /** @return list<HousekeepingBadgeCatalogueView> */
    private function catalogue(): array
    {
        $assignmentRows = DB::table('users_badges')
            ->select('badge', DB::raw('count(*) as assignment_count'), DB::raw('0 as rank_badge'))
            ->groupBy('badge');

        $rankRows = DB::table('rank_badges')
            ->select('badge', DB::raw('0 as assignment_count'), DB::raw('1 as rank_badge'));

        return DB::query()
            ->fromSub($assignmentRows->unionAll($rankRows), 'badge_catalogue')
            ->select('badge')
            ->selectRaw('sum(assignment_count) as assignment_count')
            ->selectRaw('max(rank_badge) as rank_badge')
            ->groupBy('badge')
            ->orderBy('badge')
            ->limit(500)
            ->get()
            ->map(fn (object $row): HousekeepingBadgeCatalogueView => new HousekeepingBadgeCatalogueView($row))
            ->all();
    }

    /** @return list<HousekeepingBadgeAuditView> */
    private function audit(): array
    {
        return DB::table('housekeeping_audit_log')
            ->whereIn('action', ['badge_grant', 'badge_remove', 'badge_update'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (object $row): HousekeepingBadgeAuditView => new HousekeepingBadgeAuditView($row))
            ->all();
    }

    private function hasBadge(int $userId, string $badge): bool
    {
        return DB::table('users_badges')->where('user_id', $userId)->where('badge', $badge)->exists();
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

    private function validBadgeCode(string $badge): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{1,50}$/', $badge) === 1;
    }

    private function auditBadge(string $action, int $staffId, int $targetId, string $badge, string $notes): void
    {
        DB::table('housekeeping_audit_log')->insert([
            'action' => $action,
            'user_id' => $staffId,
            'target_id' => $targetId,
            'message' => $badge,
            'extra_notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
