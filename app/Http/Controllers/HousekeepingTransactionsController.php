<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use App\Support\HousekeepingTransactionView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingTransactionsController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function lookup(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $searchQuery = (string) $request->input('searchQuery', $request->query('searchQuery', ''));

        return $this->render($template, 'housekeeping/transaction_lookup', $staff, [
            'pageName' => 'Transaction Lookup',
            'transactions' => $searchQuery !== '' ? $this->transactionsPastMonth($searchQuery) : [],
        ]);
    }

    public function trackItem(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $itemId = is_numeric($request->query('id')) ? (int) $request->query('id') : 0;

        return $this->render($template, 'housekeeping/transaction_item_lookup', $staff, [
            'pageName' => 'Transaction Lookup',
            'transactions' => $this->transactionsByItem($itemId),
        ]);
    }

    private function requirePermission(Request $request): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, 'transaction/lookup')) {
            return redirect('/'.trim((string) config('havana.housekeeping_path'), '/'));
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

    /** @return list<HousekeepingTransactionView> */
    private function transactionsPastMonth(string $searchQuery): array
    {
        if (! Schema::hasTable('users_transactions')) {
            return [];
        }

        $userId = is_numeric($searchQuery) ? (int) $searchQuery : -1;

        return DB::table('users_transactions')
            ->join('users', 'users.id', '=', 'users_transactions.user_id')
            ->where(function ($query) use ($searchQuery, $userId): void {
                $query
                    ->where(function ($inner) use ($userId): void {
                        $inner
                            ->whereMonth('users_transactions.created_at', now()->month)
                            ->whereYear('users_transactions.created_at', now()->year)
                            ->where('users_transactions.user_id', $userId);
                    })
                    ->orWhere('users.username', $searchQuery);
            })
            ->orderByDesc('users_transactions.created_at')
            ->select('users_transactions.*')
            ->get()
            ->map(fn (object $row): HousekeepingTransactionView => new HousekeepingTransactionView($row))
            ->all();
    }

    /** @return list<HousekeepingTransactionView> */
    private function transactionsByItem(int $itemId): array
    {
        if (! Schema::hasTable('users_transactions')) {
            return [];
        }

        return DB::table('users_transactions')
            ->where('item_id', (string) $itemId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (object $row): HousekeepingTransactionView => new HousekeepingTransactionView($row))
            ->all();
    }
}
