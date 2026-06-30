<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CollectablesService;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\LegacyClubGiftItem;
use App\Support\LegacyEvent;
use App\Support\LegacyUserData;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExtraHabbletController extends Controller
{
    public function removeFeedItem(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $index = $this->integerInput($request, 'feedItemIndex') ?? -1;

        if ($index >= 0) {
            $alert = DB::table('cms_alerts')
                ->where('user_id', (int) $user->id)
                ->orderByDesc('created_at')
                ->offset($index)
                ->first();

            if ($alert) {
                DB::table('cms_alerts')->where('id', (int) $alert->id)->delete();
            }
        }

        return response('');
    }

    public function loadEvents(Request $request, LegacyTemplate $template): Response
    {
        $filterId = $this->integerInput($request, 'eventTypeId');

        if ($filterId === null) {
            return response('');
        }

        $events = DB::table('rooms_events')
            ->join('rooms', 'rooms_events.room_id', '=', 'rooms.id')
            ->join('users', 'rooms_events.user_id', '=', 'users.id')
            ->where('rooms_events.category_id', $filterId)
            ->where('rooms_events.expire_time', '>', time())
            ->orderByDesc('rooms_events.expire_time')
            ->get([
                'rooms_events.*',
                'rooms.id as room_row_id',
                'rooms.name as room_name',
                'rooms.visitors_now',
                'rooms.visitors_max',
                'users.id as user_row_id',
                'users.username',
                'users.figure',
                'users.email',
                'users.motto',
                'users.credits',
                'users.rank',
                'users.club_expiration',
                'users.favourite_group',
                'users.is_online',
                'users.profile_visible',
                'users.created_at',
                'users.last_online',
            ])
            ->map(function (object $row): LegacyEvent {
                $room = (object) [
                    'id' => $row->room_row_id,
                    'name' => $row->room_name,
                    'visitors_now' => $row->visitors_now,
                    'visitors_max' => $row->visitors_max,
                ];
                $user = new User;
                $user->forceFill([
                    'username' => $row->username,
                    'figure' => $row->figure,
                    'email' => $row->email,
                    'motto' => $row->motto,
                    'credits' => $row->credits,
                    'rank' => $row->rank,
                    'club_expiration' => $row->club_expiration,
                    'favourite_group' => $row->favourite_group,
                    'is_online' => $row->is_online,
                    'profile_visible' => $row->profile_visible,
                    'created_at' => $row->created_at,
                    'last_online' => $row->last_online,
                ]);
                $user->id = (int) $row->user_row_id;
                $user->exists = true;

                return new LegacyEvent($row, $room, $user);
            })
            ->all();

        return response($template->render('habblet/load_events', ['events' => $events]));
    }

    public function habboClubConfirm(Request $request, LegacyTemplate $template): Response
    {
        $optionNumber = $this->optionNumber($request);

        if ($optionNumber < 0 || $optionNumber > 4) {
            return response('');
        }

        $choice = $this->choice($optionNumber);

        return response($template->render('habblet/habboClubConfirm', [
            'clubCredits' => $choice['credits'],
            'clubDays' => $choice['days'],
            'clubMonths' => $choice['days'] > 0 ? max(1, (int) floor($choice['days'] / 31)) : null,
            'clubType' => $optionNumber,
        ]));
    }

    public function habboClubSubscribe(Request $request, LegacyTemplate $template, HavanaConfig $config): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $choice = $this->choice($this->optionNumber($request));

        if ((int) $user->credits < $choice['credits']) {
            $message = "You don't have enough credits to complete the subscription purchase.";
        } elseif ($choice['days'] <= 0) {
            DB::table('users_statistics')->where('user_id', (int) $user->id)->update([
                'club_member_time_updated' => time() + $this->clubGiftSeconds($config),
            ]);
            $message = 'Congratulations! You have successfully subscribed to '.$config->string('site.name').' Club.';
        } else {
            $now = time();
            $currentExpiration = (int) $user->club_expiration;
            $firstSubscription = (int) $user->club_subscribed === 0;
            $newExpiration = ($currentExpiration > $now ? $currentExpiration : $now) + ($choice['days'] * 86400) + 1;
            DB::table('users')->where('id', (int) $user->id)->update([
                'credits' => (int) $user->credits - $choice['credits'],
                'club_expiration' => $newExpiration,
            ]);

            $statistics = [
                'club_member_time_updated' => $now + $this->clubGiftSeconds($config),
            ];

            if ($firstSubscription) {
                $statistics['gifts_due'] = 1;
                $statistics['club_gift_due'] = now();
            }

            DB::table('users_statistics')->where('user_id', (int) $user->id)->update($statistics);
            DB::table('users_transactions')->insert([
                'user_id' => (int) $user->id,
                'item_id' => '0',
                'catalogue_id' => '0',
                'amount' => $choice['days'],
                'description' => 'Habbo Club purchase',
                'credit_cost' => $choice['credits'],
                'pixel_cost' => 0,
                'created_at' => now(),
                'is_visible' => true,
            ]);
            $message = 'Congratulations! You have successfully subscribed to '.$config->string('site.name').' Club.';
        }

        return response($template->render('habblet/habboClubSubscribe', [
            'subscribeMsg' => $message,
        ]));
    }

    private function clubGiftSeconds(HavanaConfig $config): int
    {
        $interval = $config->integer('club.gift.interval') ?: 30;

        return match (strtoupper($config->string('club.gift.timeunit', 'DAYS'))) {
            'SECONDS' => $interval,
            'MINUTES' => $interval * 60,
            'HOURS' => $interval * 3600,
            default => $interval * 86400,
        };
    }

    public function habboClubEnddate(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);
        $context = [];

        if ($user && (int) $user->club_expiration > time()) {
            $context['hcDays'] = (int) floor(((int) $user->club_expiration - time()) / 86400);
            $context['playerDetails'] = new LegacyUserData($user);
        } elseif ($user) {
            $context['playerDetails'] = new LegacyUserData($user);
        }

        return response($template->render('habblet/habboClubEnddate', $context));
    }

    public function habboClubReminderRemove(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        DB::table('cms_alerts')
            ->where('user_id', (int) $user->id)
            ->where('alert_type', 'HC_EXPIRED')
            ->update(['is_disabled' => true]);

        return response('');
    }

    public function habboClubGift(Request $request, LegacyTemplate $template): Response
    {
        if (! $request->has(['month', 'catalogpage']) || ! ctype_digit((string) $request->input('month')) || ! ctype_digit((string) $request->input('catalogpage'))) {
            return response('');
        }

        $month = (int) $request->input('month');
        $context = $this->clubGiftContext($month);
        $request->session()->put('lastClubGiftMonth', $context['currentPage']);

        return response($template->render('habblet/habboclubgift', $context));
    }

    public function collectiblesConfirm(Request $request, LegacyTemplate $template, CollectablesService $collectables): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $collectable = $collectables->active();

        if (! $collectable) {
            return redirect('/');
        }

        return response($template->render('habblet/collectiblesConfirm', [
            'collectableName' => $collectable->activeItem->name,
            'collectableCost' => $collectable->activeItem->price_coins,
        ]));
    }

    public function collectiblesPurchase(Request $request, LegacyTemplate $template, CollectablesService $collectables): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        if ($this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        $collectable = $collectables->active();

        if (! $collectable) {
            return redirect('/');
        }

        $activeItem = $collectable->activeItem;
        $credits = (int) $user->credits;
        $pixels = (int) $user->pixels;
        $priceCoins = (int) $activeItem->price_coins;
        $pricePixels = (int) $activeItem->price_pixels;

        if ($pricePixels > $pixels) {
            $message = "Purchasing the collectable failed. You don't have enough pixels.";
        } elseif ($priceCoins > $credits) {
            $message = "Purchasing the collectable failed. You don't have enough credits.";
        } else {
            $message = DB::transaction(function () use ($user, $activeItem, $credits, $pixels, $priceCoins, $pricePixels): string {
                DB::table('users')->where('id', (int) $user->id)->update([
                    'credits' => $credits - $priceCoins,
                    'pixels' => $pixels - $pricePixels,
                ]);

                $itemIds = [];
                $amount = max(1, (int) $activeItem->amount);

                for ($i = 0; $i < $amount; $i++) {
                    $itemIds[] = (string) DB::table('items')->insertGetId([
                        'order_id' => (int) $activeItem->order_id,
                        'user_id' => (int) $user->id,
                        'room_id' => 0,
                        'definition_id' => (int) $activeItem->definition_id,
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
                    ]);
                }

                DB::table('users_transactions')->insert([
                    'user_id' => (int) $user->id,
                    'item_id' => implode(',', $itemIds),
                    'catalogue_id' => (string) $activeItem->id,
                    'amount' => $amount,
                    'description' => 'Collectable '.$activeItem->name.' purchase',
                    'credit_cost' => $priceCoins,
                    'pixel_cost' => $pricePixels,
                    'created_at' => now(),
                    'is_visible' => true,
                ]);

                return "You've successfully bought a ".$activeItem->name;
            });
        }

        return response($template->render('habblet/collectiblesPurchase', [
            'message' => $message,
        ]));
    }

    /** @return array{credits: int, days: int} */
    private function choice(int $choice): array
    {
        return match ($choice) {
            2 => ['credits' => 60, 'days' => 93],
            3 => ['credits' => 105, 'days' => 186],
            1 => ['credits' => 25, 'days' => 31],
            default => ['credits' => -1, 'days' => -1],
        };
    }

    private function optionNumber(Request $request): int
    {
        $option = (string) $request->input('optionNumber', '1');

        return preg_match('/^-?\d+$/', $option) === 1 ? (int) $option : 1;
    }

    private function currentUser(Request $request): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return User::query()->find((int) $user->id);
        }

        $userId = (int) $request->session()->get('user.id', 0);

        if ($userId > 0 && $request->session()->get('authenticated')) {
            return User::query()->find($userId);
        }

        return null;
    }

    private function hasActiveUserBan(User $user): bool
    {
        return DB::table('users_bans')
            ->where('ban_type', 'USER_ID')
            ->where('banned_value', (string) $user->id)
            ->where('is_active', true)
            ->where('banned_until', '>', Carbon::now())
            ->exists();
    }

    /**
     * @return array{pages: list<int>, currentPage: int, lastPage: int, item: LegacyClubGiftItem}
     */
    private function clubGiftContext(int $month): array
    {
        $giftOrder = $this->clubGiftOrder();
        $month = max(1, min($month, count($giftOrder)));
        $catalogPage = match (true) {
            $month >= 21 => 5,
            $month >= 17 => 4,
            $month >= 13 => 3,
            $month >= 9 => 2,
            $month >= 5 => 1,
            default => 0,
        };
        $pages = match ($catalogPage) {
            1 => [5, 6, 7, 8, 9],
            2 => [9, 10, 11, 12, 13],
            3 => [13, 14, 15, 16, 17],
            4 => [17, 18, 19, 20, 21],
            5 => [19, 20, 21, 22, 23],
            default => [1, 2, 3, 4, 5],
        };
        $sprite = $giftOrder[$month - 1];
        $name = DB::table('items_definitions')->where('sprite', $sprite)->value('name');

        return [
            'pages' => $pages,
            'currentPage' => $month,
            'lastPage' => count($giftOrder),
            'item' => new LegacyClubGiftItem($sprite, $name ? (string) $name : str_replace('_', ' ', $sprite)),
        ];
    }

    /** @return list<string> */
    private function clubGiftOrder(): array
    {
        return [
            'club_sofa',
            'hc_tv',
            'hcamme',
            'hc_crtn',
            'mocchamaster',
            'hc_crpt',
            'edicehc',
            'hc_wall_lamp',
            'doorD',
            'deal_hcrollers',
            'hcsohva',
            'hc_bkshlf',
            'hc_lmp',
            'hc_trll',
            'hc_tbl',
            'hc_machine',
            'hc_chr',
            'hc_rntgn',
            'hc_dsk',
            'hc_djset',
            'hc_lmpst',
            'hc_frplc',
            'hc_btlr',
        ];
    }

    private function integerInput(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
