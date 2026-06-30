<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyPasswordHasher;
use App\Services\LegacyTemplate;
use App\Support\LegacyAlert;
use App\Support\LegacyGroup;
use App\Support\LegacyMap;
use App\Support\LegacyMinimailRepository;
use App\Support\LegacyNewsArticle;
use App\Support\LegacyNewsCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    public function __construct(
        private readonly LegacyMinimailRepository $minimailRepository,
    ) {}

    public function me(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            $request->session()->forget(['user.id', 'authenticated']);

            return redirect('/');
        }

        if ($this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        $request->session()->put('page', 'me');
        $request->session()->forget('captcha.invalid');
        $this->logIpAddress($user->id, $request->ip() ?? '');

        $machineId = str_replace('#', '', (string) $user->machine_id);

        if ($machineId !== '' && (string) $request->cookies->get('SECURITY_KEY', '') !== $machineId) {
            cookie()->queue('SECURITY_KEY', $machineId, 60 * 48);
        }

        return response($template->render('me', [
            'playerDetails' => $user,
        ] + $this->meContext($user)));
    }

    public function welcome(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        if ($this->hasActiveUserBan($user)) {
            return redirect('/account/banned');
        }

        if ((int) $user->selected_room_id !== 0) {
            return redirect('/me');
        }

        $request->session()->put('page', 'welcome');

        return response($template->render('welcome', [
            'playerDetails' => $user,
        ]));
    }

    public function loginPopup(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'login_popup');

        $response = response($template->render('account/login'));

        $request->session()->forget('alertMessage');

        return $response;
    }

    public function reauthenticate(
        Request $request,
        LegacyPasswordHasher $hasher,
        LegacyTemplate $template,
    ): RedirectResponse|Response {
        if (! Auth::check()) {
            return redirect('/');
        }

        $user = Auth::user();

        if ($request->isMethod('post') && $user instanceof User) {
            $password = (string) $request->input('password', '');

            if ($hasher->check($password, (string) $user->password)) {
                $request->session()->put('clientAuthenticate', false);

                return redirect((string) $request->session()->get('clientRequest', '/me'));
            }

            $request->session()->put('alertMessage', "Incorrect username or password\n");
        }

        $request->session()->put('page', 'reauthenticate');

        $response = response($template->render('account/reauthenticate', [
            'playerDetails' => $user,
        ]));

        $request->session()->forget('alertMessage');

        return $response;
    }

    public function submit(Request $request, LegacyPasswordHasher $hasher, LegacyTemplate $template): RedirectResponse|Response
    {
        $request->session()->forget(['xssKey', 'xssSeed', 'xssRequested']);

        $username = strip_tags((string) $request->input('username', ''));
        $password = strip_tags((string) $request->input('password', ''));
        $remember = $request->input('_login_remember_me') === 'true';

        $user = $this->findUser($username);

        if ($user !== null && $hasher->check($password, (string) $user->password)) {
            Auth::login($user, $remember);

            $request->session()->put('authenticated', true);
            $request->session()->put('user.id', $user->id);
            $request->session()->put('captcha.invalid', false);
            $request->session()->put('clientAuthenticate', false);
            $request->session()->put('lastRequest', now()->timestamp + 1800);
            $request->session()->forget('authenticatedHousekeeping');

            if ($remember) {
                $token = (string) Str::uuid();
                $user->forceFill(['remember_token' => $token])->save();
                cookie()->queue('remember_token', $token, 60 * 24 * 31);
            } else {
                cookie()->queue(cookie()->forget('remember_token'));
            }

            cookie()->queue(cookie()->forget('vote_stamp'));

            return redirect('/security_check');
        }

        $request->session()->put('alertMessage', "Incorrect username or password\n");
        $request->session()->forget(['user.id', 'authenticated']);

        return response($template->render('account/submit', [
            'rememberMe' => $remember ? 'true' : 'false',
            'username' => $username,
        ]));
    }

    public function securityCheck(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        if (! Auth::check() && ! $request->session()->get('authenticated')) {
            return redirect('/');
        }

        return response($template->render('security_check', [
            'redirectPath' => $request->session()->get('lastBrowsedPage', '/me'),
        ]));
    }

    public function banned(Request $request, HavanaConfig $config, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $request->session()->forget('lastBrowsedPage');
        $ban = DB::table('users_bans')
            ->where('ban_type', 'USER_ID')
            ->where('banned_value', (string) $user->id)
            ->where('is_active', true)
            ->where('banned_until', '>', now())
            ->orderByDesc('banned_until')
            ->first();

        if (! $ban) {
            return redirect('/me');
        }

        $request->session()->put('page', 'banned');
        $expiresAt = Carbon::parse($ban->banned_until)->format('F j, Y g:i A');
        $siteName = $config->string('site.name');
        $message = sprintf(
            'You have been banned from %s. The reason for the ban is "%s". The ban will expire at %s.',
            $siteName,
            (string) $ban->message,
            $expiresAt,
        );

        Auth::logout();
        $user->forceFill(['remember_token' => null])->save();

        $request->session()->forget([
            'user.id',
            'authenticated',
            'authenticatedHousekeeping',
            'minimailLabel',
            'lastBrowsedPage',
        ]);

        $machineId = str_replace('#', '', (string) $user->machine_id);

        if ($machineId !== '' && (string) $request->cookies->get('SECURITY_KEY', '') !== $machineId) {
            cookie()->queue('SECURITY_KEY', $machineId, 60 * 48);
        }

        cookie()->queue(cookie()->forget('remember_token'));

        return response($template->render('account/banned', [
            'bannedMsg' => $message,
            'playerDetails' => $user,
        ]));
    }

    public function logout(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        Auth::logout();

        $user->forceFill(['remember_token' => null])->save();

        $request->session()->forget([
            'user.id',
            'authenticated',
            'authenticatedHousekeeping',
            'minimailLabel',
            'lastBrowsedPage',
        ]);
        $request->session()->put('page', 'logout');

        cookie()->queue(cookie()->forget('remember_token'));

        return response($template->render('account/logout'));
    }

    private function findUser(string $username): ?User
    {
        if ($username === '') {
            return null;
        }

        try {
            return User::query()->where('username', $username)->first();
        } catch (QueryException) {
            return null;
        }
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
            ->where('banned_until', '>', now())
            ->exists();
    }

    /** @return array<string, mixed> */
    private function meContext(User $user): array
    {
        $statistics = $this->statistics($user->id);
        $articles = $this->newsArticles();
        $minimailContext = $this->minimailRepository->messagesContext($user->id, 'inbox', 0, 0, false, false);
        $pendingGroups = $this->pendingMemberGroups($user->id);
        $newPostGroups = $this->newPostGroups($user->id);

        while (count($articles) < 5) {
            $articles[] = LegacyNewsArticle::placeholder();
        }

        $newbieRoomLayout = (int) ($statistics->newbie_room_layout ?? 0);
        $newbieNextGift = (int) ($statistics->newbie_gift ?? 0);
        $newbieGiftSeconds = max(0, (int) ($statistics->newbie_gift_time ?? 0) - time());
        $clubExpiration = (int) $user->club_expiration;
        $createdAt = $user->created_at ? Carbon::parse($user->created_at) : null;
        $hasBirthday = $createdAt !== null
            && $createdAt->format('m/d') === now()->format('m/d')
            && $createdAt->format('m/d/Y') !== now()->format('m/d/Y');
        $birthdayAge = $hasBirthday && $createdAt !== null ? $createdAt->diffInYears(now()) : 0;

        return [
            'hcDays' => $clubExpiration > time() ? (int) floor(($clubExpiration - time()) / 86400) : 0,
            'article1' => $articles[0],
            'article2' => $articles[1],
            'article3' => $articles[2],
            'article4' => $articles[3],
            'article5' => $articles[4],
            'alerts' => $this->alerts($user->id),
            'newbieRoomLayout' => $newbieRoomLayout,
            'newbieNextGift' => $newbieNextGift,
            'newbieGiftSeconds' => $newbieGiftSeconds,
            'hasBirthday' => $hasBirthday,
            'birthdayAge' => $birthdayAge,
            'birthdayPrefix' => $this->birthdayPrefix($birthdayAge),
            'tags' => $this->userTags($user->id),
            'lastOnline' => $this->friendlyDate($user->last_online),
            'tagRandomQuestion' => '',
            'events' => [],
            'groups' => $this->joinedGroups($user->id),
            'recommendedGroups' => $this->recommendedGroups(false, 10),
            'staffPickGroups' => $this->recommendedGroups(true, 10),
            'staffPickRooms' => [],
            'feedFriendRequests' => $this->friendRequestCount($user->id),
            'feedFriendsOnline' => $this->onlineFriends($user->id),
            'pendingMembers' => count($pendingGroups),
            'pendingGroups' => new LegacyMap($pendingGroups),
            'newPostsAmount' => count($newPostGroups),
            'newPosts' => new LegacyMap($newPostGroups),
            'unreadGuestbookMessages' => (int) ($statistics->guestbook_unread_messages ?? 0),
        ] + $minimailContext;
    }

    private function statistics(int $userId): object
    {
        $statistics = DB::table('users_statistics')->where('user_id', $userId)->first();

        if ($statistics !== null) {
            return $statistics;
        }

        DB::table('users_statistics')->insert([
            'user_id' => $userId,
            'activation_code' => (string) Str::uuid(),
        ]);

        return DB::table('users_statistics')->where('user_id', $userId)->first() ?? (object) [];
    }

    /** @return list<LegacyNewsArticle> */
    private function newsArticles(): array
    {
        try {
            $rows = DB::table('site_articles')
                ->leftJoin('users', 'site_articles.author_id', '=', 'users.id')
                ->where('site_articles.is_published', true)
                ->orderByDesc('site_articles.created_at')
                ->limit(5)
                ->get(['site_articles.*', 'users.username as author_name']);
        } catch (QueryException) {
            return [LegacyNewsArticle::placeholder()];
        }

        if ($rows->isEmpty()) {
            return [LegacyNewsArticle::placeholder()];
        }

        $categories = DB::table('site_articles_categories')
            ->join('article_categories', 'article_categories.id', '=', 'site_articles_categories.category_id')
            ->whereIn('site_articles_categories.article_id', $rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->get(['site_articles_categories.article_id', 'article_categories.id', 'article_categories.label', 'article_categories.category_index'])
            ->groupBy('article_id');

        return $rows->map(function ($row) use ($categories): LegacyNewsArticle {
            $articleCategories = ($categories->get((int) $row->id) ?? collect())
                ->map(fn ($category): LegacyNewsCategory => new LegacyNewsCategory(
                    (int) $category->id,
                    (string) $category->label,
                    (string) $category->category_index,
                ))
                ->all();

            return new LegacyNewsArticle(
                (int) $row->id,
                (string) $row->title,
                (int) $row->author_id,
                (string) ($row->author_override ?: ($row->author_name ?? 'Hotel Staff')),
                (string) $row->short_story,
                (string) $row->full_story,
                Carbon::parse($row->created_at),
                (string) $row->topstory,
                (string) $row->topstory_override,
                (string) $row->article_image,
                (bool) $row->is_published,
                $articleCategories,
            );
        })->all();
    }

    /** @return list<LegacyAlert> */
    private function alerts(int $userId): array
    {
        return DB::table('cms_alerts')
            ->where('user_id', $userId)
            ->where('is_disabled', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row): LegacyAlert => new LegacyAlert((string) $row->alert_type, (string) $row->message))
            ->all();
    }

    /** @return list<string> */
    private function userTags(int $userId): array
    {
        return DB::table('users_tags')
            ->where('user_id', $userId)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->orderBy('tag')
            ->pluck('tag')
            ->map(fn ($tag): string => (string) $tag)
            ->all();
    }

    private function friendRequestCount(int $userId): int
    {
        try {
            return DB::table('messenger_requests')->where('to_id', $userId)->count();
        } catch (QueryException) {
            return 0;
        }
    }

    /** @return list<User> */
    private function onlineFriends(int $userId): array
    {
        try {
            return User::query()
                ->join('messenger_friends', 'messenger_friends.from_id', '=', 'users.id')
                ->where('messenger_friends.to_id', $userId)
                ->where('users.is_online', true)
                ->where('users.online_status_visible', true)
                ->orderBy('users.username')
                ->get('users.*')
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /** @return array<int, string> */
    private function pendingMemberGroups(int $userId): array
    {
        try {
            return DB::table('groups_details')
                ->join('groups_memberships as pending', 'pending.group_id', '=', 'groups_details.id')
                ->leftJoin('groups_memberships as current_member', function ($join) use ($userId): void {
                    $join->on('current_member.group_id', '=', 'groups_details.id')
                        ->where('current_member.user_id', '=', $userId)
                        ->where('current_member.is_pending', '=', false);
                })
                ->where('pending.is_pending', true)
                ->where(function ($query) use ($userId): void {
                    $query->where('groups_details.owner_id', $userId)
                        ->orWhere('current_member.member_rank', '>=', '2');
                })
                ->groupBy('groups_details.id', 'groups_details.name')
                ->orderBy('groups_details.name')
                ->pluck('groups_details.name', 'groups_details.id')
                ->map(fn ($name): string => (string) $name)
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /** @return array<int, string> */
    private function newPostGroups(int $userId): array
    {
        try {
            return DB::table('groups_memberships')
                ->join('groups_details', 'groups_details.id', '=', 'groups_memberships.group_id')
                ->join('cms_forum_threads', 'cms_forum_threads.group_id', '=', 'groups_details.id')
                ->join('cms_forum_replies', 'cms_forum_replies.thread_id', '=', 'cms_forum_threads.id')
                ->leftJoin('cms_forums_read_replies', function ($join) use ($userId): void {
                    $join->on('cms_forums_read_replies.reply_id', '=', 'cms_forum_replies.id')
                        ->where('cms_forums_read_replies.user_id', '=', $userId);
                })
                ->where('groups_memberships.user_id', $userId)
                ->where('groups_memberships.is_pending', false)
                ->where('cms_forum_replies.is_deleted', false)
                ->where('cms_forum_replies.poster_id', '!=', $userId)
                ->whereNull('cms_forums_read_replies.reply_id')
                ->groupBy('groups_details.id', 'groups_details.name')
                ->orderBy('groups_details.name')
                ->pluck('groups_details.name', 'groups_details.id')
                ->map(fn ($name): string => (string) $name)
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    private function friendlyDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse($value)->format('M d, Y h:i:s A');
    }

    private function birthdayPrefix(int $age): string
    {
        $suffix = (string) $age;

        return match (true) {
            str_ends_with($suffix, '1') => 'st',
            str_ends_with($suffix, '2') => 'nd',
            str_ends_with($suffix, '3') => 'rd',
            default => 'th',
        };
    }

    /** @return list<LegacyGroup> */
    private function joinedGroups(int $userId): array
    {
        try {
            return DB::table('groups_memberships')
                ->join('groups_details', 'groups_details.id', '=', 'groups_memberships.group_id')
                ->where('groups_memberships.user_id', $userId)
                ->where('groups_memberships.is_pending', false)
                ->orderBy('groups_details.name')
                ->get(['groups_details.*'])
                ->map(fn ($row): LegacyGroup => $this->legacyGroup($row))
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /** @return list<LegacyGroup> */
    private function recommendedGroups(bool $staffPick, int $limit): array
    {
        try {
            $ids = DB::table('cms_recommended')
                ->where('type', 'GROUP')
                ->where('is_staff_pick', $staffPick)
                ->limit($limit)
                ->pluck('recommended_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($ids === []) {
                return [];
            }

            $groups = DB::table('groups_details')
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            return collect($ids)
                ->map(fn (int $id): ?LegacyGroup => $groups->has($id) ? $this->legacyGroup($groups->get($id)) : null)
                ->filter()
                ->values()
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    private function legacyGroup(object $row): LegacyGroup
    {
        return new LegacyGroup(
            (int) $row->id,
            (string) $row->name,
            (string) $row->description,
            (string) $row->badge,
            $row->alias !== null ? (string) $row->alias : null,
            (int) ($row->room_id ?? 0),
            [],
            [],
            (int) ($row->owner_id ?? 0),
            (string) ($row->background ?? 'bg_colour_08'),
            (int) ($row->group_type ?? 0),
            (int) ($row->forum_type ?? 0),
            (int) ($row->forum_permission ?? $row->forum_premission ?? 0),
            $row->created_at ?? null,
        );
    }

    private function logIpAddress(int $userId, string $ipAddress): void
    {
        if ($ipAddress === '') {
            return;
        }

        try {
            $latestIp = DB::table('users_ip_logs')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->value('ip_address');

            if ($latestIp !== $ipAddress) {
                DB::table('users_ip_logs')->insert([
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'created_at' => now(),
                ]);
            }
        } catch (QueryException) {
            // The legacy page still renders if IP logging fails.
        }
    }
}
