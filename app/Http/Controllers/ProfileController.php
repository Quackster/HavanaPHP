<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyFigureValidator;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use App\Support\LegacyClubGiftItem;
use App\Support\LegacyFriendCategory;
use App\Support\LegacyMessengerFriend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function profile(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $request->session()->put('page', 'me');

        $tab = (int) $request->query('tab', 0);
        $view = match ($tab) {
            2 => 'profile/change_preferences',
            3 => 'profile/change_email',
            4 => 'profile/change_password',
            5 => 'profile/friend_management',
            6 => 'profile/change_trade_settings',
            default => 'profile/change_looks',
        };

        if ($view === 'profile/change_looks' && ! $user->hasClubSubscription()) {
            return redirect('/');
        }

        $context = $this->profileContext($user, $request);

        if ($tab === 5) {
            $context += $this->friendManagementContext($user, 30, 1, -1, null);
        }

        $response = response($template->render($view, $context));

        $request->session()->forget([
            'settings.saved.successfully',
            'alertMessage',
            'alertColour',
        ]);

        return $response;
    }

    public function passwordUpdate(
        Request $request,
        LegacyTemplate $template,
        LegacyPasswordHasher $hasher,
    ): RedirectResponse|Response {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $logout = false;
        $currentPassword = (string) $request->input('currentpassword', '');
        $newPassword = (string) $request->input('newpassword', '');
        $newPasswordConfirm = (string) $request->input('newpasswordconfirm', '');
        $captcha = (string) $request->input('captcha', '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '' || $captcha === '') {
            $this->alert($request, 'Please enter all fields', 'red');
        } elseif (! $hasher->check($currentPassword, (string) $user->password)) {
            $this->alert($request, 'Your current password is invalid', 'red');
        } elseif (strlen($newPassword) < 6) {
            $this->alert($request, 'Password is too short, 6 characters minimum', 'red');
        } elseif ($newPassword !== $newPasswordConfirm) {
            $this->alert($request, "The passwords don't match", 'red');
        } elseif ((string) $request->session()->get('captcha-text', '') !== $captcha) {
            $this->alert($request, 'The security code was invalid, please try again.', 'red');
        } else {
            $this->alert($request, 'Your password has been changed successfully. You will need to login again.', 'green');
            $user->forceFill(['password' => $hasher->make($newPassword)])->save();
            $logout = true;
        }

        $response = response($template->render('profile/change_password', $this->profileContext($user->fresh(), $request)));

        $request->session()->forget(['alertMessage', 'alertColour', 'captcha-text']);

        if ($logout) {
            Auth::logout();
            $request->session()->forget(['user.id', 'authenticated']);
        }

        return $response;
    }

    public function emailUpdate(
        Request $request,
        HavanaConfig $config,
        LegacyPasswordHasher $hasher,
        LegacyTemplate $template,
    ): RedirectResponse {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $password = (string) $request->input('password', '');
        $email = (string) $request->input('email', '');
        $captcha = (string) $request->input('captcha', '');

        if ($password === '' || $captcha === '') {
            $this->alert($request, 'Please enter all fields', 'red');
        } elseif (! $hasher->check($password, (string) $user->password)) {
            $this->alert($request, 'Your current password is invalid', 'red');
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->alert($request, 'The email you entered is invalid', 'red');
        } elseif ((string) $request->session()->get('captcha-text', '') !== $captcha) {
            $this->alert($request, 'The security code was invalid, please try again.', 'red');
        } else {
            $this->alert($request, 'Your email has been changed successfully.', 'green');

            if ((string) $user->email !== $email && $config->boolean('email.smtp.enable')) {
                $activationCode = (string) Str::uuid();

                try {
                    Mail::html($template->render('account/email/email_activate', [
                        'playerId' => $user->id,
                        'playerName' => $user->username,
                        'playerEmail' => $email,
                        'activationCode' => $activationCode,
                    ]), function ($message) use ($email): void {
                        $message->to($email)->subject('Activate your account at Classic Habbo');
                    });

                    $this->updateActivationCode($user->id, $activationCode);

                    if ($config->boolean('trade.email.verification') && (bool) $user->trade_enabled) {
                        $user->forceFill(['trade_enabled' => false])->save();
                    }

                    $user->forceFill(['email' => $email])->save();
                } catch (\Throwable) {
                    // The legacy controller keeps the profile flow moving when mail delivery fails.
                }
            }
        }

        $request->session()->forget('captcha-text');

        return redirect('/profile?tab=3');
    }

    public function characterUpdate(Request $request, LegacyFigureValidator $figureValidator): RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        if (! $request->has('figureData') || ! $request->has('newGender')) {
            return redirect('/profile');
        }

        $figure = trim((string) $request->input('figureData', ''));
        $genderInput = (string) $request->input('newGender', '');
        $gender = strtoupper(substr($genderInput, 0, 1));

        if ($figure === '' || $genderInput === '' || ! in_array($gender, ['M', 'F'], true)) {
            return redirect('/profile');
        }

        if (! $figureValidator->validate($figure, $gender, $user->hasClubSubscription())) {
            return redirect('/profile');
        }

        $user->forceFill([
            'figure' => strip_tags($figure),
            'sex' => $gender,
        ])->save();

        $request->session()->put('settings.saved.successfully', 'true');

        return redirect('/profile');
    }

    public function action(): RedirectResponse
    {
        return redirect('/profile');
    }

    public function profileUpdate(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $motto = substr((string) $request->input('motto', ''), 0, 32);

        $user->forceFill([
            'motto' => $motto,
            'profile_visible' => (string) $request->input('visibility', '') === 'EVERYONE',
            'online_status_visible' => (string) $request->input('showOnlineStatus', '') === 'true',
            'wordfilter_enabled' => (string) $request->input('wordFilterSetting', '') !== 'false',
            'allow_friend_requests' => (string) $request->input('allowFriendRequests', '') === 'true',
            'allow_stalking' => (string) $request->input('followFriendSetting', '') === 'true',
        ])->save();

        $request->session()->put('settings.saved.successfully', 'true');

        return redirect('/profile?tab=2');
    }

    public function securitySettingUpdate(
        Request $request,
        HavanaConfig $config,
        LegacyPasswordHasher $hasher,
    ): RedirectResponse {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $password = (string) $request->input('password', '');

        if ($password === '') {
            $this->alert($request, 'You did not enter a password', 'red');
        } elseif (! $hasher->check($password, (string) $user->password)) {
            $this->alert($request, 'Your current password is invalid', 'red');
        } elseif ($config->boolean('trade.email.verification') && ! $this->accountActivated($user->id)) {
            $this->alert($request, 'You must verify your email before enabling trade.', 'red');
        } elseif ($this->hasUserTradePass($user)) {
            $this->alert($request, 'This email is already used for a trade pass.', 'red');
        } else {
            $tradeSetting = (string) $request->input('tradingsetting', '') === 'true';
            $user->forceFill(['trade_enabled' => $tradeSetting])->save();
            $this->alert($request, 'Security settings updated successfully', 'green');
        }

        return redirect('/profile?tab=6');
    }

    public function verify(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $request->session()->put('page', 'me');

        $response = response($template->render('profile/verify_email', $this->profileContext($user, $request)));

        $request->session()->forget(['alertMessage', 'alertColour']);

        return $response;
    }

    public function sendEmail(Request $request, HavanaConfig $config, LegacyTemplate $template): RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        if ($this->accountActivated($user->id)) {
            $this->alert($request, 'Your email is already activated', 'red');

            return redirect('/profile/verify');
        }

        $activationCode = (string) Str::uuid();
        $this->updateActivationCode($user->id, $activationCode);

        if ($config->boolean('email.smtp.enable')) {
            $this->alert($request, 'A verification email has been sent to your email address', 'green');

            try {
                Mail::html($template->render('account/email/email_activate', [
                    'playerId' => $user->id,
                    'playerName' => $user->username,
                    'playerEmail' => $user->email,
                    'activationCode' => $activationCode,
                ]), function ($message) use ($user): void {
                    $message->to((string) $user->email)->subject('Activate your account at Classic Habbo');
                });
            } catch (\Throwable) {
                // Activation code persistence is the compatibility-critical side effect.
            }
        }

        return redirect('/profile/verify');
    }

    public function club(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $request->session()->put('page', 'me');

        $context = [
            'playerDetails' => $user,
            'clubChoiceCredits1' => 20,
            'clubChoiceDays1' => 31,
            'clubChoiceCredits2' => 50,
            'clubChoiceDays2' => 93,
            'clubChoiceCredits3' => 80,
            'clubChoiceDays3' => 186,
            'hcDays' => 0,
            'hcSinceMonths' => 0,
        ];

        if ($user->hasClubSubscription()) {
            $memberTime = (int) DB::table('users_statistics')
                ->where('user_id', $user->id)
                ->value('club_member_time');

            $context['hcDays'] = (int) floor(((int) $user->club_expiration - time()) / 86400);
            $context['hcSinceMonths'] = max(0, intdiv((int) floor($memberTime / 86400), 31));
        }

        return response($template->render('club', $context + $this->clubGiftContext(
            (int) $request->session()->get('lastClubGiftMonth', 1),
            $request,
        )));
    }

    public function wardrobeStore(Request $request, LegacyFigureValidator $figureValidator): JsonResponse|RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $slotInput = (string) $request->input('slot', '');

        if (! ctype_digit($slotInput)) {
            return redirect('/');
        }

        $slot = (int) $slotInput;
        $figure = strip_tags((string) $request->input('figure', ''));
        $gender = strtoupper(strip_tags((string) $request->input('gender', '')));

        if ($gender === '') {
            $gender = 'M';
        }

        if ($slot < 1 || $slot > 5 || $figure === '' || ! $figureValidator->validate($figure, $gender, $user->hasClubSubscription())) {
            return redirect('/');
        }

        DB::table('users_wardrobes')->updateOrInsert(
            ['user_id' => $user->id, 'slot_id' => $slot],
            ['figure' => $figure, 'sex' => $gender],
        );

        return response()->json([
            'slot' => (string) $slot,
            'u' => $this->figureLink($figure),
            'f' => $figure,
            'g' => 77,
        ]);
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

    /** @return array<string, mixed> */
    private function profileContext(User $user, Request $request): array
    {
        $tradeEnabled = (bool) $user->trade_enabled;

        $context = [
            'playerDetails' => $user,
            'accountActivated' => $this->accountActivated($user->id),
            'settingsSavedAlert' => $request->session()->has('settings.saved.successfully'),
            'randomNumber' => random_int(0, PHP_INT_MAX),
            'onlineStatusEnabled' => (bool) $user->online_status_visible ? 'checked="checked"' : '',
            'onlineStatusDisabled' => (bool) $user->online_status_visible ? '' : 'checked="checked"',
            'followFriendEnabled' => (bool) $user->allow_stalking ? 'checked="checked"' : '',
            'followFriendDisabled' => (bool) $user->allow_stalking ? '' : 'checked="checked"',
            'profileVisibleEnabled' => (bool) $user->profile_visible ? 'checked="checked"' : '',
            'profileVisibleDisabled' => (bool) $user->profile_visible ? '' : 'checked="checked"',
            'allowFriendRequests' => (bool) $user->allow_friend_requests ? 'checked="true"' : '',
            'wordFilterSetting' => (bool) $user->wordfilter_enabled ? '' : 'checked="true"',
            'tradeEnabled' => $tradeEnabled ? 'checked="checked"' : '',
            'tradeDisabled' => $tradeEnabled ? '' : 'checked="checked"',
            'canUseTrade' => ! app(HavanaConfig::class)->boolean('trade.email.verification') || $this->accountActivated($user->id),
            'wardrobe1' => false,
            'wardrobe2' => false,
            'wardrobe3' => false,
            'wardrobe4' => false,
            'wardrobe5' => false,
            'figureHasClub' => false,
        ];

        if ($user->hasClubSubscription()) {
            $wardrobes = DB::table('users_wardrobes')
                ->where('user_id', $user->id)
                ->whereBetween('slot_id', [1, 5])
                ->get(['slot_id', 'figure', 'sex']);

            foreach ($wardrobes as $wardrobe) {
                $slot = (int) $wardrobe->slot_id;
                $figure = (string) $wardrobe->figure;
                $sex = strtoupper(substr((string) $wardrobe->sex, 0, 1)) ?: 'M';

                $context['wardrobe'.$slot] = true;
                $context['wardrobeUrl'.$slot] = $this->figureLink($figure);
                $context['wardrobeFigure'.$slot] = $figure;
                $context['wardrobeSex'.$slot] = $sex;
            }
        }

        return $context;
    }

    private function accountActivated(int $userId): bool
    {
        $activationCode = DB::table('users_statistics')
            ->where('user_id', $userId)
            ->value('activation_code');

        return $activationCode === null || $activationCode === '';
    }

    private function updateActivationCode(int $userId, string $activationCode): void
    {
        DB::table('users_statistics')->updateOrInsert(
            ['user_id' => $userId],
            ['activation_code' => $activationCode],
        );
    }

    private function hasUserTradePass(User $user): bool
    {
        return DB::table('users')
            ->join('users_statistics', 'users.id', '=', 'users_statistics.user_id')
            ->where('users.email', $user->email)
            ->where('users.id', '<>', $user->id)
            ->whereNull('users_statistics.activation_code')
            ->where('users.trade_enabled', true)
            ->exists();
    }

    private function alert(Request $request, string $message, string $colour): void
    {
        $request->session()->put('alertMessage', $message);
        $request->session()->put('alertColour', $colour);
    }

    /** @return array<string, mixed> */
    private function friendManagementContext(User $user, int $limit, int $currentPage, int $categoryId, ?string $searchString): array
    {
        $categories = $this->friendCategories($user->id);
        $friends = $this->friends($user->id, $limit, $currentPage, $categoryId, $searchString, $categories);
        $friendsCount = $this->friendsCount($user->id, $searchString);
        $pages = max(1, (int) ceil($friendsCount / $limit));

        return [
            'friends' => $friends,
            'categories' => $categories,
            'currentPage' => $currentPage,
            'pageLimit' => $limit,
            'firstPage' => $currentPage >= 2 ? 1 : -1,
            'previousPage' => $currentPage > 1 ? $currentPage - 1 : -1,
            'nextPage' => $pages >= ($currentPage + 1) ? $currentPage + 1 : -1,
            'lastPage' => $pages >= ($currentPage + 2) ? $pages : -1,
        ];
    }

    /** @return list<LegacyFriendCategory> */
    private function friendCategories(int $userId): array
    {
        return DB::table('messenger_categories')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row): LegacyFriendCategory => new LegacyFriendCategory(
                (int) $row->id,
                (string) $row->name,
            ))
            ->all();
    }

    /**
     * @param  list<LegacyFriendCategory>  $categories
     * @return list<LegacyMessengerFriend>
     */
    private function friends(int $userId, int $limit, int $page, int $categoryId, ?string $searchString, array $categories): array
    {
        $query = DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', $userId);

        if ($searchString !== null) {
            $query->where('users.username', 'like', $searchString.'%');
        }

        $validCategoryIds = collect($categories)
            ->map(fn (LegacyFriendCategory $category): int => $category->getId())
            ->all();

        return $query
            ->orderByDesc('users.last_online')
            ->forPage($page, $limit)
            ->get([
                'users.id',
                'users.username',
                'users.last_online',
                'messenger_friends.category_id',
            ])
            ->map(function (object $row) use ($userId, $validCategoryIds): object {
                if ((int) $row->category_id !== 0 && ! in_array((int) $row->category_id, $validCategoryIds, true)) {
                    DB::table('messenger_friends')
                        ->where('from_id', (int) $row->id)
                        ->where('to_id', $userId)
                        ->update(['category_id' => 0]);

                    $row->category_id = 0;
                }

                return $row;
            })
            ->filter(fn (object $row): bool => $categoryId <= -1 || (int) $row->category_id === $categoryId)
            ->map(fn ($row): LegacyMessengerFriend => new LegacyMessengerFriend(
                (int) $row->id,
                (string) $row->username,
                (int) $row->category_id,
                $row->last_online,
            ))
            ->all();
    }

    private function friendsCount(int $userId, ?string $searchString): int
    {
        $query = DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', $userId);

        if ($searchString !== null) {
            $query->where('users.username', 'like', $searchString.'%');
        }

        return $query->count();
    }

    /** @return array<string, mixed> */
    private function clubGiftContext(int $month, Request $request): array
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

        $request->session()->put('lastClubGiftMonth', $month);

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

    private function figureLink(string $figure): string
    {
        return rtrim(app(HavanaConfig::class)->string('site.path'), '/')
            .'/habbo-imaging/avatarimage?figure='.$figure.'&size=s&direction=4&head_direction=4&crr=0&gesture=sml&frame=1';
    }
}
