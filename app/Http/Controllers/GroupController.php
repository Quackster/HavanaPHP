<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\LegacyDiscussionReply;
use App\Support\LegacyDiscussionTopic;
use App\Support\LegacyGroup;
use App\Support\LegacyGroupMember;
use App\Support\LegacyRoom;
use App\Support\LegacyRoomData;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupController extends Controller
{
    private const GAME_GROUP_ALIASES = [
        'battleball_rebound',
        'lido',
        'snow_storm',
        'wobble_squabble',
    ];

    public function show(Request $request, LegacyTemplate $template, string $group): Response|RedirectResponse
    {
        $groupDto = $this->resolveGroup($group, $request->path(), '/groups/%s');

        if ($groupDto instanceof RedirectResponse) {
            return $groupDto;
        }

        if (! $groupDto) {
            abort(404);
        }

        $this->setCurrentPage($request, $groupDto);
        DB::table('groups_details')->where('id', $groupDto->id)->increment('views');

        return response($template->render('groups', $this->groupContext($request, $groupDto) + [
            'stickers' => [],
            'tags' => [],
            'guestbookSetting' => 'public',
            'stickerLimit' => 200,
            'room' => $this->room($groupDto->getRoomId()),
        ]));
    }

    public function discussions(Request $request, LegacyTemplate $template, string $group, ?int $page = null): Response|RedirectResponse
    {
        $groupDto = $this->resolveGroup($group, $request->path(), '/groups/%s/discussions');

        if ($groupDto instanceof RedirectResponse) {
            return $groupDto;
        }

        if (! $groupDto) {
            abort(404);
        }

        $this->setCurrentPage($request, $groupDto);

        $pageNumber = max(1, (int) ($page ?? 1));
        $perPage = max(1, app(HavanaConfig::class)->integer('discussions.per.page', 20));
        $visibility = $this->forumVisibility($request, $groupDto);
        $topicCount = $visibility['canViewForum']
            ? DB::table('cms_forum_threads')->where('group_id', $groupDto->id)->count()
            : 0;
        $pages = max(1, (int) ceil($topicCount / $perPage));

        return response($template->render('groups/view_discussions', $this->groupContext($request, $groupDto) + [
            'canViewForum' => $visibility['canViewForum'],
            'canPostForum' => $visibility['canPostForum'],
            'discussionTopics' => $visibility['canViewForum'] ? $this->topics($groupDto->id, $pageNumber, $perPage) : [],
        ] + $this->pagination($pageNumber, $pages)));
    }

    public function discussion(Request $request, LegacyTemplate $template, string $group, int $thread, ?int $page = null): Response|RedirectResponse
    {
        $redirectPattern = '/groups/%s/discussions/'.$thread.'/id'.($page !== null ? '/page/'.$page : '');
        $groupDto = $this->resolveGroup($group, $request->path(), $redirectPattern);

        if ($groupDto instanceof RedirectResponse) {
            return $groupDto;
        }

        if (! $groupDto) {
            abort(404);
        }

        $topic = $this->topic($groupDto->id, $thread);

        if (! $topic) {
            abort(404);
        }

        $this->setCurrentPage($request, $groupDto);

        if (! $request->session()->get('hasViewedDiscussion'.$thread)) {
            $request->session()->put('hasViewedDiscussion'.$thread, true);
            DB::table('cms_forum_threads')->where('id', $thread)->increment('views');
        }

        $pageNumber = max(1, (int) ($page ?? 1));
        $perPage = max(1, app(HavanaConfig::class)->integer('discussions.replies.per.page', 20));
        $replyCount = DB::table('cms_forum_replies')->where('thread_id', $thread)->count();
        $pages = max(1, (int) ceil($replyCount / $perPage));
        $visibility = $this->forumVisibility($request, $groupDto);
        $hasMessage = ! $visibility['canViewForum'];

        return response($template->render('groups/discussion', $this->groupContext($request, $groupDto) + [
            'hasMessage' => $hasMessage,
            'message' => $hasMessage ? 'View forums denied. Please check that you are logged in and have the appropriate rights to view the forums. If you are logged in and still can\'t view the forums, the group may be private. If so, you need to join the group in order to view the forums. ' : '',
            'canViewForum' => $visibility['canViewForum'],
            'canReplyForum' => $visibility['canPostForum'] && $topic->isOpen(),
            'discussionTopic' => $topic,
            'discussionId' => $topic->getId(),
            'replyList' => $hasMessage ? [] : $this->replies($thread, $pageNumber, $perPage),
            'firstReply' => (int) (DB::table('cms_forum_replies')->where('thread_id', $thread)->orderBy('id')->value('id') ?? 0),
            'hasTopicAdmin' => $this->hasTopicAdmin($request, $groupDto, $topic),
        ] + $this->pagination($pageNumber, $pages)));
    }

    private function resolveGroup(string $match, string $path, string $redirectPattern): LegacyGroup|RedirectResponse|null
    {
        if (ctype_digit($match) && str_contains($path, '/id')) {
            $group = $this->groupById((int) $match);

            if ($group && $group->getAlias() !== '') {
                return redirect(sprintf($redirectPattern, $group->getAlias()));
            }

            return $group;
        }

        return $this->groupByAlias($match);
    }

    private function groupById(int $id): ?LegacyGroup
    {
        $row = DB::table('groups_details')->where('id', $id)->first();

        return $row ? $this->groupFromRow($row) : null;
    }

    private function groupByAlias(string $alias): ?LegacyGroup
    {
        $row = DB::table('groups_details')->where('alias', $alias)->first();

        return $row ? $this->groupFromRow($row) : null;
    }

    private function groupFromRow(object $row): LegacyGroup
    {
        $members = DB::table('groups_memberships')
            ->where('group_id', (int) $row->id)
            ->where('is_pending', false)
            ->get(['user_id', 'member_rank'])
            ->mapWithKeys(fn ($member): array => [(int) $member->user_id => (int) $member->member_rank])
            ->all();

        $members[(int) $row->owner_id] = max(3, $members[(int) $row->owner_id] ?? 0);

        $pendingMembers = DB::table('groups_memberships')
            ->where('group_id', (int) $row->id)
            ->where('is_pending', true)
            ->pluck('user_id')
            ->mapWithKeys(fn ($userId): array => [(int) $userId => true])
            ->all();

        return new LegacyGroup(
            (int) $row->id,
            (string) $row->name,
            (string) $row->description,
            (string) $row->badge,
            $row->alias !== null ? (string) $row->alias : null,
            (int) $row->room_id,
            $members,
            $pendingMembers,
            (int) $row->owner_id,
            property_exists($row, 'background') ? (string) $row->background : 'bg_colour_08',
            property_exists($row, 'group_type') ? (int) $row->group_type : 0,
            property_exists($row, 'forum_type') ? (int) $row->forum_type : 0,
            property_exists($row, 'forum_premission') ? (int) $row->forum_premission : (property_exists($row, 'forum_permission') ? (int) $row->forum_permission : 0),
        );
    }

    /** @return array<string, mixed> */
    private function groupContext(Request $request, LegacyGroup $group): array
    {
        $user = $this->currentUser($request);
        $hasMember = $user ? $group->isMember((int) $user->id) : false;

        return [
            'group' => $group,
            'editMode' => false,
            'hasMember' => $hasMember,
            'groupMember' => $user ? new LegacyGroupMember($group->getMember((int) $user->id)->getRankId(), (int) $user->favourite_group) : new LegacyGroupMember(0),
            'playerDetails' => $user ? new LegacyUserData($user) : null,
        ];
    }

    /** @return array{canViewForum: bool, canPostForum: bool} */
    private function forumVisibility(Request $request, LegacyGroup $group): array
    {
        $user = $this->currentUser($request);
        $rank = $user ? $group->getMember((int) $user->id)->getRankId() : 0;
        $forumType = $group->getForumType()->getId();
        $forumPermission = $group->getForumPermission()->getId();

        return [
            'canViewForum' => $forumType === 0 || ($user !== null && $rank > 0),
            'canPostForum' => $user !== null && ($forumPermission === 0 || $rank >= $forumPermission),
        ];
    }

    /** @return list<LegacyDiscussionTopic> */
    private function topics(int $groupId, int $pageNumber, int $perPage): array
    {
        $replyCounts = DB::table('cms_forum_replies')
            ->select('thread_id', DB::raw('count(*) as replies'), DB::raw('max(created_at) as last_message_at'))
            ->groupBy('thread_id');

        return DB::table('cms_forum_threads')
            ->leftJoin('users as creator', 'creator.id', '=', 'cms_forum_threads.poster_id')
            ->leftJoinSub($replyCounts, 'reply_counts', 'reply_counts.thread_id', '=', 'cms_forum_threads.id')
            ->leftJoin('cms_forum_replies as last_reply', function ($join): void {
                $join->on('last_reply.thread_id', '=', 'cms_forum_threads.id')
                    ->on('last_reply.created_at', '=', 'reply_counts.last_message_at');
            })
            ->leftJoin('users as last_user', 'last_user.id', '=', 'last_reply.poster_id')
            ->where('cms_forum_threads.group_id', $groupId)
            ->orderByDesc('cms_forum_threads.is_stickied')
            ->orderByDesc(DB::raw('coalesce(reply_counts.last_message_at, cms_forum_threads.created_at)'))
            ->offset(($pageNumber - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'cms_forum_threads.id',
                'cms_forum_threads.topic_title',
                'cms_forum_threads.poster_id',
                'cms_forum_threads.is_open',
                'cms_forum_threads.is_stickied',
                'cms_forum_threads.views',
                'cms_forum_threads.created_at',
                DB::raw('coalesce(reply_counts.replies, 0) as reply_count'),
                DB::raw('coalesce(reply_counts.last_message_at, cms_forum_threads.created_at) as last_message_at'),
                DB::raw('coalesce(creator.username, "Habbo") as creator_name'),
                DB::raw('coalesce(last_user.username, creator.username, "Habbo") as last_reply_name'),
            ])
            ->map(fn ($row): LegacyDiscussionTopic => $this->topicFromRow($row))
            ->all();
    }

    private function topic(int $groupId, int $threadId): ?LegacyDiscussionTopic
    {
        $row = DB::table('cms_forum_threads')
            ->leftJoin('users as creator', 'creator.id', '=', 'cms_forum_threads.poster_id')
            ->leftJoin('cms_forum_replies as last_reply', function ($join): void {
                $join->on('last_reply.thread_id', '=', 'cms_forum_threads.id')
                    ->whereRaw('last_reply.id = (select max(id) from cms_forum_replies where thread_id = cms_forum_threads.id)');
            })
            ->leftJoin('users as last_user', 'last_user.id', '=', 'last_reply.poster_id')
            ->where('cms_forum_threads.group_id', $groupId)
            ->where('cms_forum_threads.id', $threadId)
            ->first([
                'cms_forum_threads.id',
                'cms_forum_threads.topic_title',
                'cms_forum_threads.poster_id',
                'cms_forum_threads.is_open',
                'cms_forum_threads.is_stickied',
                'cms_forum_threads.views',
                'cms_forum_threads.created_at',
                DB::raw('(select count(*) from cms_forum_replies where thread_id = cms_forum_threads.id) as reply_count'),
                DB::raw('coalesce(last_reply.created_at, cms_forum_threads.created_at) as last_message_at'),
                DB::raw('coalesce(creator.username, "Habbo") as creator_name'),
                DB::raw('coalesce(last_user.username, creator.username, "Habbo") as last_reply_name'),
            ]);

        return $row ? $this->topicFromRow($row) : null;
    }

    private function topicFromRow(object $row): LegacyDiscussionTopic
    {
        $replyCount = max(1, (int) $row->reply_count);
        $replyPages = max(1, (int) ceil($replyCount / max(1, app(HavanaConfig::class)->integer('discussions.replies.per.page', 20))));

        return new LegacyDiscussionTopic(
            (int) $row->id,
            (string) $row->topic_title,
            (int) $row->poster_id,
            (string) $row->creator_name,
            (bool) $row->is_open,
            (bool) $row->is_stickied,
            (int) $row->views,
            $replyCount,
            (string) $row->last_reply_name,
            $row->created_at,
            $row->last_message_at,
            $replyPages,
        );
    }

    /** @return list<LegacyDiscussionReply> */
    private function replies(int $threadId, int $pageNumber, int $perPage): array
    {
        return DB::table('cms_forum_replies')
            ->leftJoin('users', 'users.id', '=', 'cms_forum_replies.poster_id')
            ->where('cms_forum_replies.thread_id', $threadId)
            ->orderBy('cms_forum_replies.id')
            ->offset(($pageNumber - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'cms_forum_replies.id',
                'cms_forum_replies.poster_id',
                'cms_forum_replies.message',
                'cms_forum_replies.is_edited',
                'cms_forum_replies.is_deleted',
                'cms_forum_replies.created_at',
                'cms_forum_replies.modified_at',
                DB::raw('coalesce(users.username, "Habbo") as username'),
                DB::raw('coalesce(users.figure, "") as figure'),
                DB::raw('coalesce(users.is_online, 0) as is_online'),
            ])
            ->map(fn ($row): LegacyDiscussionReply => new LegacyDiscussionReply(
                (int) $row->id,
                (int) $row->poster_id,
                (string) $row->username,
                (string) $row->figure,
                (string) $row->message,
                (bool) $row->is_edited,
                (bool) $row->is_deleted,
                $row->created_at,
                $row->modified_at,
                (bool) $row->is_online,
                $this->forumMessagesFor((int) $row->poster_id),
            ))
            ->all();
    }

    private function forumMessagesFor(int $userId): int
    {
        return DB::table('cms_forum_replies')->where('poster_id', $userId)->count();
    }

    private function hasTopicAdmin(Request $request, LegacyGroup $group, LegacyDiscussionTopic $topic): bool
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return false;
        }

        return (int) $user->id === $topic->getCreatorId() || $group->hasAdministrator((int) $user->id) || (int) $user->rank >= 5;
    }

    /** @return array<string, int> */
    private function pagination(int $pageNumber, int $pages): array
    {
        $data = [
            'currentPage' => $pageNumber,
            'pages' => $pages,
        ];

        for ($i = 1; $i <= 5; $i++) {
            $previous = $pageNumber - $i;
            $next = $pageNumber + $i;
            $data['previousPage'.$i] = $previous >= 1 ? $previous : -1;
            $data['nextPage'.$i] = $next > 1 && $next <= $pages ? $next : -1;
        }

        return $data;
    }

    private function room(int $roomId): ?LegacyRoom
    {
        if ($roomId <= 0 || ! Schema::hasTable('rooms')) {
            return null;
        }

        $row = DB::table('rooms')->where('id', $roomId)->first();

        if (! $row) {
            return null;
        }

        $owner = User::query()->find((int) $row->owner_id);

        return new LegacyRoom(new LegacyRoomData(
            (int) $row->id,
            (string) $row->name,
            (string) $row->description,
            $owner instanceof User ? (string) $owner->username : 'Habbo',
            (int) $row->visitors_now,
            (int) $row->visitors_max,
        ));
    }

    private function setCurrentPage(Request $request, LegacyGroup $group): void
    {
        $request->session()->put('page', in_array($group->getAlias(), self::GAME_GROUP_ALIASES, true) ? 'games' : 'community');
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
}
