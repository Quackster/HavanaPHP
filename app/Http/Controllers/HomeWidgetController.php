<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyAvatarListFriend;
use App\Support\LegacyBadge;
use App\Support\LegacyGroup;
use App\Support\LegacyGroupMember;
use App\Support\LegacyGuestbookEntry;
use App\Support\LegacyGuestbookWidget;
use App\Support\LegacyRatingWidget;
use App\Support\LegacyUserData;
use App\Support\LegacyWordfilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeWidgetController extends Controller
{
    public function guestbookPreview(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('homes/widget/guestbook/preview', [
            'message' => LegacyGuestbookEntry::formatPreviewMessage((string) $request->input('message', '')),
            'author' => new LegacyUserData($user),
            'playerDetails' => new LegacyUserData($user),
            'formattedDate' => now()->format('M j, Y g:i:s A'),
        ]));
    }

    public function guestbookAdd(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widgetId = $this->integerInput($request, 'widgetId');
        $widget = $widgetId !== null ? LegacyGuestbookWidget::find($widgetId) : null;

        if (! $widget || ! $widget->isPlaced() || ! $widget->isPostingAllowed((int) $user->id)) {
            return response('');
        }

        $homeId = $widget->isGroupWidget() ? 0 : $widget->getUserId();
        $groupId = $widget->isGroupWidget() ? $widget->getGroupId() : 0;
        $message = mb_substr((string) $request->input('message', ''), 0, 200);

        if ($homeId > 0 && $homeId !== (int) $user->id) {
            DB::table('users_statistics')->where('user_id', $homeId)->increment('guestbook_unread_messages');
        }

        $entry = LegacyWordfilter::filterSentence($message) === $message
            ? LegacyGuestbookEntry::create((int) $user->id, $homeId, $groupId, $message)
            : new LegacyGuestbookEntry(random_int(1, PHP_INT_MAX), (int) $user->id, $homeId, $groupId, $message, now());

        return response($template->render('homes/widget/guestbook/add', [
            'entry' => $entry,
            'sticker' => $widget,
            'canDeleteEntries' => $widget->canDeleteEntries((int) $user->id),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function guestbookRemove(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widgetId = $this->integerInput($request, 'widgetId');
        $widget = $widgetId !== null ? LegacyGuestbookWidget::find($widgetId) : null;

        if (! $widget || ! $widget->isPlaced()) {
            return response('');
        }

        $entryId = $this->integerInput($request, 'entryId');
        $entry = $entryId !== null ? DB::table('cms_guestbook_entries')->where('id', $entryId)->first() : null;

        if (! $entry || (! $widget->canDeleteEntries((int) $user->id) && (int) $entry->user_id !== (int) $user->id)) {
            return response('');
        }

        $homeId = $widget->isGroupWidget() ? 0 : $widget->getUserId();
        $groupId = $widget->isGroupWidget() ? $widget->getGroupId() : 0;

        DB::table('cms_guestbook_entries')
            ->where('id', (int) $entry->id)
            ->where('home_id', $homeId)
            ->where('group_id', $groupId)
            ->delete();

        return response($template->render('homes/widget/guestbook_widget', [
            'editMode' => $request->session()->has('homeEditSession') || $request->session()->has('groupEditSession'),
            'sticker' => $widget,
            'playerDetails' => new LegacyUserData($user),
            'group' => $this->guestbookGroupContext($widget),
        ]));
    }

    public function guestbookConfigure(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widgetId = $this->integerInput($request, 'widgetId');
        $widget = $widgetId !== null ? LegacyGuestbookWidget::find($widgetId) : null;

        if (! $widget || ! $widget->isPlaced() || $widget->ownerId() !== (int) $user->id) {
            return response('');
        }

        $widget->toggleGuestbookState();

        return response("var el = $(\"guestbook-type\");\n".
            "if (el) {\n".
            "\tif (el.hasClassName(\"public\")) {\n".
            "\t\tel.className = \"private\";\n".
            "\t\tnew Effect.Pulsate(el,\n".
            "\t\t\t{ duration: 1.0, afterFinish : function() { Element.setOpacity(el, 1); } }\n".
            "\t\t);\t\t\t\t\t\t\n".
            "\t} else {\t\t\t\t\t\t\n".
            "\t\tnew Effect.Pulsate(el,\n".
            "\t\t\t{ duration: 1.0, afterFinish : function() { Element.setOpacity(el, 0); el.className = \"public\"; } }\n".
            "\t\t);\t\t\t\t\t\t\n".
            "\t}\n".
            '}', 200, ['Content-Type' => 'text/javascript']);
    }

    public function rate(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $ratingId = $this->integerInput($request, 'ratingId') ?? -1;
        $widget = $this->ratingWidget($ratingId);
        $rating = $this->integerInput($request, 'givenRate') ?? -1;

        if (! $widget || $rating < 1 || $rating > 5 || $widget['home_id'] === (int) $user->id) {
            return response('');
        }

        $hasRated = DB::table('homes_ratings')
            ->where('user_id', (int) $user->id)
            ->where('home_id', $widget['home_id'])
            ->exists();

        if ($hasRated) {
            return response('');
        }

        DB::table('homes_ratings')->insert([
            'user_id' => (int) $user->id,
            'home_id' => $widget['home_id'],
            'rating' => $rating,
        ]);

        return response($template->render('homes/widget/habblet/rate', [
            'sticker' => new LegacyRatingWidget($widget['id'], $widget['home_id']),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function resetRatings(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $widget = $this->ratingWidget((int) $request->query('ratingId', 0));

        if (! $widget || $widget['home_id'] !== (int) $user->id) {
            return response('');
        }

        DB::table('homes_ratings')->where('home_id', (int) $user->id)->delete();

        return response($template->render('homes/widget/habblet/rate', [
            'sticker' => new LegacyRatingWidget($widget['id'], $widget['home_id']),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function groupInfo(Request $request, LegacyTemplate $template): Response
    {
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $group) {
            return response('');
        }

        return response($template->render('homes/widget/habblet/groupinfo', [
            'group' => $group,
        ]));
    }

    public function memberSearchPaging(Request $request, LegacyTemplate $template): Response
    {
        $widgetId = $this->integerInput($request, 'widgetId');
        $widget = $widgetId !== null ? $this->widgetRow($widgetId) : null;

        if (! $widget) {
            return response('');
        }

        $group = $this->groupById((int) $widget->group_id);

        if (! $group) {
            return response('');
        }

        $pageNumber = max(1, (int) $request->input('pageNumber', 1));
        $limit = 32;
        $members = $this->members((int) $group->id, $pageNumber, $limit, (string) $request->input('searchString', ''));
        $memberCount = DB::table('groups_memberships')->where('group_id', $group->id)->where('is_pending', false)->count() + 1;
        $pages = max(1, (int) ceil($memberCount / $limit));

        return response($template->render('homes/widget/habblet/membersearchpaging', [
            'sticker' => $this->simpleSticker((int) $widget->id),
            'group' => $group,
            'members' => $memberCount,
            'membersList' => $members,
            'currentPage' => $pageNumber,
            'pages' => $pages,
        ]));
    }

    public function avatarInfo(Request $request, LegacyTemplate $template): Response
    {
        $accountId = $this->integerInput($request, 'anAccountId');
        $user = $accountId !== null ? User::query()->find($accountId) : null;

        if (! $user) {
            return response('');
        }

        return response($template->render('homes/widget/habblet/avatarinfo', [
            'avatar' => new LegacyUserData($user),
        ]));
    }

    public function badgePaging(Request $request, LegacyTemplate $template): Response
    {
        $widgetId = $this->integerInput($request, 'widgetId');
        $widget = $widgetId !== null ? $this->widgetRow($widgetId) : null;

        if (! $widget) {
            return response('');
        }

        $pageNumber = max(1, (int) $request->input('pageNumber', 1));
        $limit = 16;
        $badgeCount = DB::table('users_badges')->where('user_id', (int) $widget->user_id)->count();
        $pages = max(1, (int) ceil($badgeCount / $limit));

        if ($pageNumber > $pages) {
            $pageNumber = $pages;
        }

        $badgeList = DB::table('users_badges')
            ->where('user_id', (int) $widget->user_id)
            ->offset(($pageNumber - 1) * $limit)
            ->limit($limit)
            ->pluck('badge')
            ->map(fn ($badge): LegacyBadge => new LegacyBadge((string) $badge))
            ->all();

        return response($template->render('homes/widget/habblet/badgepaging', [
            'sticker' => $this->simpleSticker((int) $widget->id),
            'user' => $this->simpleUser((int) $widget->user_id),
            'pages' => $pages,
            'showLast' => $pageNumber < $pages,
            'badgeList' => $badgeList,
            'currentPage' => $pageNumber,
        ]));
    }

    public function friendSearchPaging(Request $request, LegacyTemplate $template): Response
    {
        $widgetId = $this->integerInput($request, 'widgetId');
        $widget = $widgetId !== null ? $this->widgetRow($widgetId) : null;

        if (! $widget) {
            return response('');
        }

        $pageNumber = max(1, (int) $request->input('pageNumber', 1));
        $searchString = trim((string) $request->input('searchString', ''));
        $limit = 32;
        $baseQuery = DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', (int) $widget->user_id);

        if ($searchString !== '') {
            $baseQuery->where('users.username', 'like', $searchString.'%');
        }

        $filteredFriends = (clone $baseQuery)->count();
        $friends = DB::table('messenger_friends')
            ->where('to_id', (int) $widget->user_id)
            ->count();
        $pages = max(1, (int) ceil($filteredFriends / $limit));
        $friendsList = $baseQuery
            ->orderByDesc('users.last_online')
            ->offset(($pageNumber - 1) * $limit)
            ->limit($limit)
            ->get(['users.id', 'users.username', 'users.figure', 'users.last_online'])
            ->map(fn (object $row): LegacyAvatarListFriend => new LegacyAvatarListFriend(
                (int) $row->id,
                (string) $row->username,
                (string) $row->figure,
                $row->last_online,
            ))
            ->all();

        return response($template->render('homes/widget/habblet/friendsearchpaging', [
            'sticker' => $this->simpleSticker((int) $widget->id),
            'pages' => $pages,
            'friends' => $friends,
            'friendsList' => $friendsList,
            'currentPage' => $pageNumber,
        ]));
    }

    /** @return array{id: int, home_id: int}|null */
    private function ratingWidget(int $widgetId): ?array
    {
        $row = DB::table('cms_stickers')->where('id', $widgetId)->first(['id', 'user_id']);

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'home_id' => (int) $row->user_id,
        ];
    }

    /** @return list<LegacyGroupMember> */
    private function members(int $groupId, int $pageNumber, int $limit, string $searchString = ''): array
    {
        $query = DB::table('groups_memberships')
            ->join('users', 'users.id', '=', 'groups_memberships.user_id')
            ->where('groups_memberships.group_id', $groupId)
            ->where('groups_memberships.is_pending', false);

        $searchString = trim($searchString);

        if ($searchString !== '') {
            $query->where('users.username', 'like', $searchString.'%');
        }

        return $query
            ->offset(($pageNumber - 1) * $limit)
            ->limit($limit)
            ->get(['groups_memberships.member_rank', 'users.*'])
            ->sortByDesc(fn (object $row): int => strtotime((string) $row->last_online) ?: 0)
            ->values()
            ->map(function ($row): LegacyGroupMember {
                $attributes = (array) $row;
                unset($attributes['member_rank']);
                $user = new User;
                $user->forceFill($attributes);
                $user->exists = true;
                $user->id = (int) $row->id;

                return new LegacyGroupMember((int) $row->member_rank, (int) $row->favourite_group, $user);
            })
            ->all();
    }

    private function groupById(int $groupId): ?LegacyGroup
    {
        $row = DB::table('groups_details')->where('id', $groupId)->first();

        if (! $row) {
            return null;
        }

        return new LegacyGroup(
            (int) $row->id,
            (string) $row->name,
            (string) $row->description,
            (string) $row->badge,
            $row->alias !== null ? (string) $row->alias : null,
            (int) $row->room_id,
            [],
            [],
            (int) $row->owner_id,
            property_exists($row, 'background') ? (string) $row->background : 'bg_colour_08',
            property_exists($row, 'group_type') ? (int) $row->group_type : 0,
            property_exists($row, 'forum_type') ? (int) $row->forum_type : 0,
            property_exists($row, 'forum_premission') ? (int) $row->forum_premission : 0,
            property_exists($row, 'created_at') ? $row->created_at : null,
        );
    }

    private function widgetRow(int $widgetId): ?object
    {
        return DB::table('cms_stickers')->where('id', $widgetId)->first();
    }

    private function simpleSticker(int $widgetId): object
    {
        return new class($widgetId)
        {
            public function __construct(private readonly int $id) {}

            public function getId(): int
            {
                return $this->id;
            }
        };
    }

    private function simpleUser(int $userId): object
    {
        return new class($userId)
        {
            public function __construct(public readonly int $id) {}
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

    private function guestbookGroupContext(LegacyGuestbookWidget $widget): object
    {
        return new class($widget->getGroupId())
        {
            public function __construct(private readonly int $id) {}

            public function getId(): int
            {
                return $this->id;
            }
        };
    }
}
