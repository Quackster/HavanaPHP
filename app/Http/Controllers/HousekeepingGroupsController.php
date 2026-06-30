<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\HousekeepingGroupMemberView;
use App\Support\HousekeepingGroupReplyView;
use App\Support\HousekeepingGroupThreadView;
use App\Support\HousekeepingGroupView;
use App\Support\HousekeepingManagerView;
use App\Support\HousekeepingPlayerView;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingGroupsController extends Controller
{
    private const SESSION_KEY = 'housekeeping.authenticated';

    private const USER_ID_KEY = 'user.id';

    public function groups(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $query = trim((string) $request->query('query', ''));

        return $this->render($template, 'housekeeping/groups', $staff, [
            'pageName' => 'Groups',
            'query' => $query,
            'groups' => $this->groupsList($query),
        ]);
    }

    public function edit(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id === null || $id <= 0) {
            $this->alert($request, 'Group ID is required', 'danger');

            return redirect($this->housekeepingUrl('/groups'));
        }

        $group = $this->group($id);

        if ($group === null) {
            $this->alert($request, 'Group does not exist', 'danger');

            return redirect($this->housekeepingUrl('/groups'));
        }

        if ($request->isMethod('post')) {
            $name = trim((string) $request->input('name', ''));
            $alias = trim((string) $request->input('alias', ''));
            $ownerId = (int) $request->input('owner_id', 0);
            $roomId = (int) $request->input('room_id', 0);
            $forumType = (int) $request->input('forum_type', 0);
            $forumPermission = (int) $request->input('forum_premission', 0);

            if ($name === '') {
                $this->alert($request, 'Group name cannot be blank', 'danger');
            } elseif (! User::query()->whereKey($ownerId)->exists()) {
                $this->alert($request, 'Owner ID must be an existing user ID', 'danger');
            } elseif ($roomId > 0 && ! DB::table('rooms')->where('id', $roomId)->exists()) {
                $this->alert($request, 'Home room must be 0 or an existing room ID', 'danger');
            } elseif ($this->aliasConflict($alias, $id)) {
                $this->alert($request, 'Group alias is already in use', 'danger');
            } elseif ($forumType < 0 || $forumType > 1) {
                $this->alert($request, 'Forum type must be public or private', 'danger');
            } elseif ($forumPermission < 0 || $forumPermission > 2) {
                $this->alert($request, 'Forum posting permission is invalid', 'danger');
            } else {
                DB::table('groups_details')->where('id', $id)->update([
                    'name' => $name,
                    'description' => trim((string) $request->input('description', '')),
                    'owner_id' => $ownerId,
                    'room_id' => $roomId,
                    'badge' => trim((string) $request->input('badge', '')),
                    'recommended' => $request->boolean('recommended'),
                    'background' => trim((string) $request->input('background', '')),
                    'views' => (int) $request->input('views', 0),
                    'topics' => (int) $request->input('topics', 0),
                    'group_type' => (int) $request->input('group_type', 0),
                    'forum_type' => $forumType,
                    'forum_premission' => $forumPermission,
                    'alias' => $alias !== '' ? $alias : null,
                ]);
                $this->alert($request, 'Group saved successfully', 'success');

                return redirect($this->housekeepingUrl('/groups/edit?id='.$id));
            }
        }

        return $this->render($template, 'housekeeping/group_edit', $staff, [
            'pageName' => 'Edit Group',
            'group' => $group,
            'members' => $this->members($id),
            'threads' => $this->threads($id),
            'replies' => $this->replies($id),
        ]);
    }

    public function member(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $groupId = $this->integerInputOrQuery($request, 'group_id');
        $userId = $this->integerInputOrQuery($request, 'user_id');

        if ($request->query('delete') !== null) {
            if ($groupId !== null && $userId !== null) {
                DB::table('groups_memberships')->where('group_id', $groupId)->where('user_id', $userId)->delete();
                $this->alert($request, 'Group member removed successfully', 'success');
            }
        } else {
            $rank = $this->integerInput($request, 'member_rank');

            if ($groupId === null || $userId === null || $rank === null || $rank < 1 || $rank > 3) {
                $this->alert($request, 'Member rank must be 1, 2, or 3', 'danger');
            } else {
                DB::table('groups_memberships')
                    ->where('group_id', $groupId)
                    ->where('user_id', $userId)
                    ->update([
                        'member_rank' => (string) $rank,
                        'is_pending' => $request->boolean('is_pending'),
                    ]);
                $this->alert($request, 'Group member saved successfully', 'success');
            }
        }

        return redirect($this->housekeepingUrl('/groups/edit?id='.($groupId ?? 0)));
    }

    public function thread(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $groupId = $this->integerInputOrQuery($request, 'group_id');
        $threadId = $this->integerInputOrQuery($request, 'thread_id');

        if ($request->query('delete') !== null) {
            if ($groupId !== null && $threadId !== null) {
                DB::table('cms_forum_replies')->where('thread_id', $threadId)->delete();
                DB::table('cms_forum_threads')->where('id', $threadId)->delete();
                $this->alert($request, 'Discussion thread deleted successfully', 'success');
            }
        } else {
            $topicTitle = trim((string) $request->input('topic_title', ''));

            if ($groupId === null || $threadId === null || $topicTitle === '') {
                $this->alert($request, 'Thread title cannot be blank', 'danger');
            } else {
                DB::table('cms_forum_threads')->where('id', $threadId)->update([
                    'topic_title' => $topicTitle,
                    'is_open' => $request->boolean('is_open'),
                    'is_stickied' => $request->boolean('is_stickied'),
                ]);
                $this->alert($request, 'Discussion thread saved successfully', 'success');
            }
        }

        return redirect($this->housekeepingUrl('/groups/edit?id='.($groupId ?? 0)));
    }

    public function reply(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $groupId = $this->integerQuery($request, 'group_id');
        $replyId = $this->integerQuery($request, 'reply_id');

        if ($request->query('delete') !== null) {
            if ($groupId !== null && $replyId !== null) {
                DB::table('cms_forums_read_replies')->where('reply_id', $replyId)->delete();
                DB::table('cms_forum_replies')->where('id', $replyId)->delete();
                $this->alert($request, 'Discussion reply deleted successfully', 'success');
            }
        } else {
            $deleted = filter_var($request->query('deleted', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
            if ($groupId !== null && $replyId !== null) {
                DB::table('cms_forum_replies')->where('id', $replyId)->update(['is_deleted' => $deleted]);
                $this->alert($request, $deleted ? 'Discussion reply hidden successfully' : 'Discussion reply restored successfully', 'success');
            }
        }

        return redirect($this->housekeepingUrl('/groups/edit?id='.($groupId ?? 0)));
    }

    public function staffPick(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null && $id > 0) {
            if (! DB::table('groups_details')->where('id', $id)->exists()) {
                $this->alert($request, 'Group does not exist', 'danger');
            } else {
                $enabled = filter_var($request->query('enabled', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
                DB::table('cms_recommended')
                    ->where('recommended_id', $id)
                    ->where('type', 'GROUP')
                    ->where('is_staff_pick', true)
                    ->delete();

                if ($enabled) {
                    DB::table('cms_recommended')->insert([
                        'recommended_id' => $id,
                        'type' => 'GROUP',
                        'is_staff_pick' => true,
                    ]);
                }
                $this->alert($request, $enabled ? 'Group added to staff picks' : 'Group removed from staff picks', 'success');
            }
        }

        return redirect($this->housekeepingUrl('/groups'));
    }

    public function delete(Request $request): RedirectResponse
    {
        $staff = $this->requirePermission($request);

        if ($staff instanceof RedirectResponse) {
            return $staff;
        }

        $id = $this->integerQuery($request, 'id');

        if ($id !== null && $id > 0) {
            $threadIds = DB::table('cms_forum_threads')->where('group_id', $id)->pluck('id')->map(fn ($threadId): int => (int) $threadId)->all();
            $replyIds = $threadIds !== []
                ? DB::table('cms_forum_replies')->whereIn('thread_id', $threadIds)->pluck('id')->map(fn ($replyId): int => (int) $replyId)->all()
                : [];

            if ($replyIds !== []) {
                DB::table('cms_forums_read_replies')->whereIn('reply_id', $replyIds)->delete();
            }

            if ($threadIds !== []) {
                DB::table('cms_forum_replies')->whereIn('thread_id', $threadIds)->delete();
                DB::table('cms_forum_threads')->whereIn('id', $threadIds)->delete();
            }

            DB::table('cms_guestbook_entries')->where('group_id', $id)->delete();
            DB::table('groups_memberships')->where('group_id', $id)->delete();
            DB::table('groups_edit_sessions')->where('group_id', $id)->delete();
            DB::table('cms_recommended')->where('recommended_id', $id)->where('type', 'GROUP')->delete();

            if (Schema::hasColumn('users', 'favourite_group')) {
                DB::table('users')->where('favourite_group', $id)->update(['favourite_group' => 0]);
            }

            DB::table('groups_details')->where('id', $id)->delete();
            $this->alert($request, 'Group deleted successfully', 'success');
        }

        return redirect($this->housekeepingUrl('/groups'));
    }

    private function requirePermission(Request $request): User|RedirectResponse
    {
        $user = $this->currentHousekeepingUser($request);

        if ($user === null || ! (new HousekeepingManagerView)->hasPermission((int) $user->rank, 'groups/manage')) {
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

    private function integerQuery(Request $request, string $key): ?int
    {
        return $this->integerValue($request->query($key));
    }

    private function integerInput(Request $request, string $key): ?int
    {
        return $this->integerValue($request->input($key));
    }

    private function integerInputOrQuery(Request $request, string $key): ?int
    {
        if ($request->input($key) !== null) {
            return $this->integerInput($request, $key);
        }

        return $this->integerQuery($request, $key);
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

    /** @return list<HousekeepingGroupView> */
    private function groupsList(string $query): array
    {
        $groups = $this->adminGroupQuery()->orderByDesc('groups_details.id')->limit(100);

        if ($query !== '') {
            $normalised = mb_strtolower($query);
            $groupId = filter_var($normalised, FILTER_VALIDATE_INT);
            $groups->where(function ($builder) use ($normalised, $groupId): void {
                if ($groupId !== false) {
                    $builder->where('groups_details.id', (int) $groupId);
                }

                $builder->orWhereRaw('lower(groups_details.name) like ?', ['%'.$normalised.'%'])
                    ->orWhereRaw('lower(users.username) like ?', ['%'.$normalised.'%'])
                    ->orWhereRaw('lower(groups_details.alias) like ?', ['%'.$normalised.'%']);
            });
        }

        return $groups->get()
            ->map(fn (object $row): HousekeepingGroupView => new HousekeepingGroupView($row))
            ->all();
    }

    private function group(int $id): ?HousekeepingGroupView
    {
        $row = $this->adminGroupQuery()->where('groups_details.id', $id)->first();

        return $row !== null ? new HousekeepingGroupView($row) : null;
    }

    private function adminGroupQuery(): Builder
    {
        return DB::table('groups_details')
            ->leftJoin('users', 'groups_details.owner_id', '=', 'users.id')
            ->select('groups_details.*', 'users.username as owner_name')
            ->selectRaw('(select count(*) from groups_memberships where groups_memberships.group_id = groups_details.id and is_pending = 0) as member_count')
            ->selectRaw('(select count(*) from groups_memberships where groups_memberships.group_id = groups_details.id and is_pending = 1) as pending_count')
            ->selectRaw('(select count(*) from cms_forum_threads where cms_forum_threads.group_id = groups_details.id) as thread_count')
            ->selectRaw("exists(select 1 from cms_recommended where cms_recommended.recommended_id = groups_details.id and cms_recommended.type = 'GROUP' and cms_recommended.is_staff_pick = 1) as staff_pick");
    }

    private function aliasConflict(string $alias, int $groupId): bool
    {
        return $alias !== ''
            && DB::table('groups_details')->where('alias', $alias)->where('id', '!=', $groupId)->exists();
    }

    /** @return list<HousekeepingGroupMemberView> */
    private function members(int $groupId): array
    {
        return DB::table('groups_memberships')
            ->leftJoin('users', 'groups_memberships.user_id', '=', 'users.id')
            ->where('groups_memberships.group_id', $groupId)
            ->orderByDesc('groups_memberships.is_pending')
            ->orderByDesc('groups_memberships.member_rank')
            ->orderBy('users.username')
            ->limit(100)
            ->get(['groups_memberships.*', 'users.username'])
            ->map(fn (object $row): HousekeepingGroupMemberView => new HousekeepingGroupMemberView($row))
            ->all();
    }

    /** @return list<HousekeepingGroupThreadView> */
    private function threads(int $groupId): array
    {
        return DB::table('cms_forum_threads')
            ->leftJoin('users', 'cms_forum_threads.poster_id', '=', 'users.id')
            ->where('cms_forum_threads.group_id', $groupId)
            ->orderByDesc('cms_forum_threads.modified_at')
            ->limit(50)
            ->get([
                'cms_forum_threads.*',
                'users.username as poster_name',
                DB::raw('(select count(*) from cms_forum_replies where cms_forum_replies.thread_id = cms_forum_threads.id) as reply_count'),
            ])
            ->map(fn (object $row): HousekeepingGroupThreadView => new HousekeepingGroupThreadView($row))
            ->all();
    }

    /** @return list<HousekeepingGroupReplyView> */
    private function replies(int $groupId): array
    {
        return DB::table('cms_forum_replies')
            ->join('cms_forum_threads', 'cms_forum_replies.thread_id', '=', 'cms_forum_threads.id')
            ->leftJoin('users', 'cms_forum_replies.poster_id', '=', 'users.id')
            ->where('cms_forum_threads.group_id', $groupId)
            ->orderByDesc('cms_forum_replies.created_at')
            ->limit(50)
            ->get([
                'cms_forum_replies.*',
                'cms_forum_threads.topic_title',
                'users.username as poster_name',
            ])
            ->map(fn (object $row): HousekeepingGroupReplyView => new HousekeepingGroupReplyView($row))
            ->all();
    }
}
