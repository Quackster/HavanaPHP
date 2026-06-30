<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyGroup;
use App\Support\LegacyMessengerFriend;
use App\Support\LegacyRoom;
use App\Support\LegacyRoomData;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class QuickmenuController extends Controller
{
    public function groups(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('quickmenu/groups', [
            'playerDetails' => new LegacyUserData($user),
            'groups' => $this->joinedGroups($user->id),
        ]));
    }

    public function rooms(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('quickmenu/rooms', [
            'rooms' => $this->ownedRooms($user->id),
        ]));
    }

    public function friends(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $friends = $this->friendsFor($user->id);
        $onlineFriends = collect($friends)
            ->filter(fn (LegacyMessengerFriend $friend): bool => $friend->isOnline())
            ->sortByDesc(fn (LegacyMessengerFriend $friend): int => $friend->getLastOnline())
            ->take(10)
            ->values()
            ->all();
        $offlineFriends = collect($friends)
            ->reject(fn (LegacyMessengerFriend $friend): bool => $friend->isOnline())
            ->sortByDesc(fn (LegacyMessengerFriend $friend): int => $friend->getLastOnline())
            ->take(10)
            ->values()
            ->all();

        return response($template->render('quickmenu/friends_all', [
            'onlineFriends' => $onlineFriends,
            'offlineFriends' => $offlineFriends,
        ]));
    }

    /** @return list<LegacyGroup> */
    private function joinedGroups(int $userId): array
    {
        $rows = DB::table('groups_details')
            ->leftJoin('groups_memberships', 'groups_memberships.group_id', '=', 'groups_details.id')
            ->where('groups_details.owner_id', $userId)
            ->orWhere(function ($query) use ($userId): void {
                $query->where('groups_memberships.user_id', $userId)
                    ->where('groups_memberships.is_pending', false);
            })
            ->get([
                'groups_details.id',
                'groups_details.name',
                'groups_details.description',
                'groups_details.owner_id',
                'groups_details.badge',
                'groups_details.alias',
                'groups_details.room_id',
            ])
            ->unique('id')
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $memberRows = DB::table('groups_memberships')
            ->whereIn('group_id', $rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->where('is_pending', false)
            ->get(['group_id', 'user_id', 'member_rank'])
            ->groupBy('group_id');

        return $rows
            ->map(function ($row) use ($memberRows, $userId): LegacyGroup {
                $members = ($memberRows->get((int) $row->id) ?? collect())
                    ->mapWithKeys(fn ($member): array => [(int) $member->user_id => (int) $member->member_rank])
                    ->all();

                if ((int) $row->owner_id === $userId) {
                    $members[$userId] = 3;
                }

                return new LegacyGroup(
                    (int) $row->id,
                    (string) $row->name,
                    (string) $row->description,
                    (string) $row->badge,
                    $row->alias === null ? null : (string) $row->alias,
                    (int) $row->room_id,
                    $members,
                );
            })
            ->sortByDesc(fn (LegacyGroup $group): int => $group->getMemberCount(false))
            ->values()
            ->all();
    }

    /** @return list<LegacyRoom> */
    private function ownedRooms(int $userId): array
    {
        $ownerName = (string) User::query()->whereKey($userId)->value('username');

        return DB::table('rooms')
            ->where('owner_id', (string) $userId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row): LegacyRoom => new LegacyRoom(new LegacyRoomData(
                (int) $row->id,
                (string) $row->name,
                (string) $row->description,
                $ownerName,
                (int) $row->visitors_now,
                (int) $row->visitors_max,
            )))
            ->all();
    }

    /** @return list<LegacyMessengerFriend> */
    private function friendsFor(int $userId): array
    {
        return DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', $userId)
            ->get([
                'users.id',
                'users.username',
                'users.last_online',
                'users.is_online',
                'users.online_status_visible',
                'messenger_friends.category_id',
            ])
            ->map(fn ($row): LegacyMessengerFriend => new LegacyMessengerFriend(
                (int) $row->id,
                (string) $row->username,
                (int) $row->category_id,
                $row->last_online,
                (bool) $row->is_online && (bool) $row->online_status_visible,
            ))
            ->all();
    }

    private function currentUser(Request $request): ?User
    {
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
}
