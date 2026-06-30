<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\LegacyDiscussionReply;
use App\Support\LegacyDiscussionTopic;
use App\Support\LegacyGroup;
use App\Support\LegacyUserData;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiscussionActionController extends Controller
{
    public function pingSession(Request $request): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response('')->header('X-JSON', '{"privilegeLevel":"1"}');
    }

    public function newTopic(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/discussions/newpost'));
    }

    public function saveTopic(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $message = trim((string) $request->input('message', ''));
        $topicName = trim((string) $request->input('topicName', ''));

        if ($topicName === '' || $message === '') {
            return response($template->render('groups/discussion_replies', [
                'hasMessage' => true,
                'message' => 'Please supply a valid message.',
            ]));
        }

        if ((string) $request->session()->get('captcha-text', '') !== (string) $request->input('captcha', '')) {
            $request->session()->forget('captcha-text');

            return response('')->header('X-JSON', '{"captchaError":"true"}');
        }

        $latestMessage = DB::table('cms_forum_replies')
            ->where('poster_id', (int) $user->id)
            ->orderByDesc('created_at')
            ->value('message');

        if (is_string($latestMessage) && str_starts_with($latestMessage, $message)) {
            return response($template->render('groups/discussion_replies', [
                'hasMessage' => true,
                'message' => 'Do not spam the forums',
            ]));
        }

        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? DB::table('groups_details')->where('id', $groupId)->first() : null;

        if (! $group || ! $this->canCreateTopic($group, (int) $user->id, (int) $user->rank)) {
            return redirect('/');
        }

        $topicName = substr($topicName, 0, 32);
        $topicId = DB::table('cms_forum_threads')->insertGetId([
            'topic_title' => $topicName,
            'poster_id' => (int) $user->id,
            'is_open' => true,
            'is_stickied' => false,
            'views' => 0,
            'group_id' => (int) $group->id,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        DB::table('cms_forum_replies')->insert([
            'thread_id' => $topicId,
            'message' => $message,
            'poster_id' => (int) $user->id,
            'is_edited' => false,
            'is_deleted' => false,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        DB::table('groups_details')->where('id', (int) $group->id)->increment('topics');
        $request->session()->forget('captcha-text');

        return response($this->groupLink($group).'/discussions/'.$topicId.'/id');
    }

    public function previewTopic(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('groups/discussions/previewtopic', $this->previewContext($user) + [
            'topicName' => (string) $request->input('topicName', ''),
            'topicMessage' => $this->formatMessage((string) $request->input('message', '')),
        ]));
    }

    public function previewPost(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $topic = $groupId !== null && $topicId !== null
            ? DB::table('cms_forum_threads')
                ->where('id', $topicId)
                ->where('group_id', $groupId)
                ->first()
            : null;

        if (! $topic) {
            return redirect('/');
        }

        return response($template->render('groups/discussions/previewpost', $this->previewContext($user) + [
            'postName' => 'RE: '.$topic->topic_title,
            'postMessage' => $this->formatMessage((string) $request->input('message', '')),
        ]));
    }

    public function openTopicSettings(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $topic = $groupId !== null && $topicId !== null ? $this->topic($groupId, $topicId) : null;

        if (! $topic) {
            return redirect('/');
        }

        return response($template->render('groups/discussions/opentopicsettings', [
            'topic' => $topic,
        ]));
    }

    public function confirmDeleteTopic(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/discussions/confirm_delete_topic'));
    }

    public function deleteTopic(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $group = $groupId !== null ? DB::table('groups_details')->where('id', $groupId)->first() : null;
        $topic = $group && $topicId !== null ? $this->topic((int) $group->id, $topicId) : null;

        if (! $group || ! $topic) {
            return redirect('/');
        }

        if (! $this->canModerateTopic($group, $topic, $user)) {
            return response('');
        }

        DB::table('cms_forum_threads')->where('id', $topicId)->where('group_id', (int) $group->id)->delete();
        DB::table('cms_forum_replies')->where('thread_id', $topicId)->delete();
        DB::table('groups_details')->where('id', (int) $group->id)->update([
            'topics' => DB::raw('case when topics > 0 then topics - 1 else 0 end'),
        ]);

        return response('SUCCESS');
    }

    public function saveTopicSettings(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $group = $groupId !== null ? DB::table('groups_details')->where('id', $groupId)->first() : null;
        $topic = $group && $topicId !== null ? $this->topic((int) $group->id, $topicId) : null;

        if (! $group || ! $topic) {
            return redirect('/');
        }

        if (! $this->canModerateTopic($group, $topic, $user)) {
            return response('');
        }

        $topicTitle = substr((string) $request->input('topicName', ''), 0, 32);
        DB::table('cms_forum_threads')->where('id', $topicId)->where('group_id', (int) $group->id)->update([
            'topic_title' => $topicTitle,
            'is_open' => (int) $request->input('topicClosed', 0) === 0,
            'is_stickied' => (int) $request->input('topicSticky', 0) === 1,
            'modified_at' => now(),
        ]);

        return response($template->render('groups/discussion_replies', $this->fragmentContext(
            $request,
            $group,
            $topicId,
            max(1, (int) $request->input('page', 1))
        )));
    }

    public function updatePost(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $postId = $this->integerInput($request, 'postId');
        $group = $groupId !== null ? DB::table('groups_details')->where('id', $groupId)->first() : null;
        $topic = $group && $topicId !== null ? $this->topic((int) $group->id, $topicId) : null;
        $reply = $topic && $postId !== null ? $this->reply($topic->getId(), $postId) : null;
        $pageNumber = max(1, (int) $request->input('page', 1));

        if (! $group || ! $topic || ! $topic->isOpen() || ! $reply) {
            return redirect('/');
        }

        if ((string) $request->session()->get('captcha-text', '') === (string) $request->input('captcha', '')) {
            $request->session()->forget('captcha-text');

            return response('')->header('X-JSON', '{"captchaError":"true"}');
        }

        if ($reply->getUserId() !== (int) $user->id) {
            return redirect('/');
        }

        DB::table('cms_forum_replies')->where('id', $reply->getId())->update([
            'message' => (string) $request->input('message', ''),
            'is_edited' => (int) $user->rank < 5,
            'modified_at' => now(),
        ]);
        $request->session()->forget('captcha-text');

        return response($template->render('groups/discussion_replies', $this->fragmentContext(
            $request,
            $group,
            $topic->getId(),
            $pageNumber
        )));
    }

    public function deletePost(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $postId = $this->integerInput($request, 'postId');
        $group = $groupId !== null ? DB::table('groups_details')->where('id', $groupId)->first() : null;
        $topic = $group && $topicId !== null ? $this->topic((int) $group->id, $topicId) : null;
        $reply = $topic && $postId !== null ? $this->reply($topic->getId(), $postId) : null;
        $pageNumber = max(1, (int) $request->input('page', 1));

        if (! $group || ! $topic || ! $topic->isOpen() || ! $reply) {
            return redirect('/');
        }

        if (! $this->canDeleteReply($group, $reply, $user)) {
            return response('');
        }

        if ($reply->getUserId() !== (int) $user->id || (int) $user->rank >= 5) {
            DB::table('cms_forum_replies')->where('id', $reply->getId())->delete();
        } else {
            DB::table('cms_forum_replies')->where('id', $reply->getId())->update([
                'is_deleted' => true,
                'is_edited' => true,
                'modified_at' => now(),
            ]);
        }

        return response($template->render('groups/discussion_replies', $this->fragmentContext(
            $request,
            $group,
            $topic->getId(),
            $pageNumber
        )));
    }

    public function savePost(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $topicId = $this->integerInput($request, 'topicId');
        $group = $groupId !== null ? DB::table('groups_details')->where('id', $groupId)->first() : null;
        $topic = $group && $topicId !== null ? $this->topic((int) $group->id, $topicId) : null;

        if ((string) $request->session()->get('captcha-text', '') !== (string) $request->input('captcha', '')) {
            $request->session()->forget('captcha-text');

            return response('')->header('X-JSON', '{"captchaError":"true"}');
        }

        $message = (string) $request->input('message', '');

        if (trim($message) === '') {
            return response($template->render('groups/discussion_replies', [
                'hasMessage' => true,
                'message' => 'Please supply a valid message.',
            ]));
        }

        if (! $group || ! $topic || (! $topic->isOpen() && (int) $user->rank < 5)) {
            return redirect('/');
        }

        $latestMessage = DB::table('cms_forum_replies')
            ->where('poster_id', (int) $user->id)
            ->orderByDesc('created_at')
            ->value('message');

        if (is_string($latestMessage) && str_starts_with($latestMessage, $message)) {
            return response($template->render('groups/discussion_replies', [
                'hasMessage' => true,
                'message' => 'Do not spam the forums',
            ]));
        }

        if (! $this->canReply($group, (int) $user->id, (int) $user->rank)) {
            return redirect('/');
        }

        DB::table('cms_forum_replies')->insert([
            'thread_id' => $topic->getId(),
            'message' => $message,
            'poster_id' => (int) $user->id,
            'is_edited' => false,
            'is_deleted' => false,
            'created_at' => now(),
            'modified_at' => now(),
        ]);
        $request->session()->forget('captcha-text');

        $perPage = $this->repliesPerPage();
        $replyCount = DB::table('cms_forum_replies')->where('thread_id', $topic->getId())->count();
        $pageNumber = max(1, (int) ceil($replyCount / $perPage));

        return response($template->render('groups/discussion_replies', $this->fragmentContext(
            $request,
            $group,
            $topic->getId(),
            $pageNumber
        )));
    }

    private function canCreateTopic(object $group, int $userId, int $rank): bool
    {
        $memberRank = $this->memberRank($group, $userId);
        $forumType = (int) ($group->forum_type ?? 0);
        $forumPermission = (int) ($group->forum_premission ?? ($group->forum_permission ?? 0));

        if ($rank >= 5) {
            return true;
        }

        if (($forumType === 1 || $forumPermission > 0) && $memberRank <= 0) {
            return false;
        }

        return $forumPermission !== 2 || $memberRank >= 2;
    }

    private function canReply(object $group, int $userId, int $rank): bool
    {
        if ($rank >= 5) {
            return true;
        }

        return (int) ($group->forum_type ?? 0) !== 1 || $this->memberRank($group, $userId) > 0;
    }

    private function canModerateTopic(object $group, LegacyDiscussionTopic $topic, User $user): bool
    {
        return $topic->getCreatorId() === (int) $user->id
            || (int) $user->rank >= 5
            || $this->memberRank($group, (int) $user->id) >= 2;
    }

    private function canDeleteReply(object $group, LegacyDiscussionReply $reply, User $user): bool
    {
        return $reply->getUserId() === (int) $user->id
            || (int) $user->rank >= 5
            || $this->memberRank($group, (int) $user->id) >= 2;
    }

    private function memberRank(object $group, int $userId): int
    {
        if ((int) $group->owner_id === $userId) {
            return 3;
        }

        return (int) (DB::table('groups_memberships')
            ->where('group_id', (int) $group->id)
            ->where('user_id', $userId)
            ->where('is_pending', false)
            ->value('member_rank') ?? 0);
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

    /** @return array<string, mixed> */
    private function fragmentContext(Request $request, object $group, int $topicId, int $pageNumber): array
    {
        $user = $this->currentUser($request);
        $groupDto = $this->groupFromRow($group);
        $topic = $this->topic((int) $group->id, $topicId);
        $perPage = $this->repliesPerPage();
        $replyCount = DB::table('cms_forum_replies')->where('thread_id', $topicId)->count();
        $pages = max(1, (int) ceil($replyCount / $perPage));
        $pageNumber = max(1, min($pageNumber, $pages));
        $canReply = $user !== null && $topic !== null && $topic->isOpen() && $this->canReply($group, (int) $user->id, (int) $user->rank);

        return [
            'group' => $groupDto,
            'hasMessage' => false,
            'message' => '',
            'canReplyForum' => $canReply,
            'canViewForum' => true,
            'discussionTopic' => $topic,
            'discussionId' => $topic?->getId() ?? $topicId,
            'replyList' => $this->replies($topicId, $pageNumber, $perPage),
            'firstReply' => (int) (DB::table('cms_forum_replies')->where('thread_id', $topicId)->orderBy('id')->value('id') ?? 0),
            'hasTopicAdmin' => $user !== null && $topic !== null && $this->canModerateTopic($group, $topic, $user),
            'playerDetails' => $user ? new LegacyUserData($user) : null,
        ] + $this->pagination($pageNumber, $pages);
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

        return new LegacyGroup(
            (int) $row->id,
            (string) $row->name,
            (string) $row->description,
            (string) $row->badge,
            $row->alias !== null ? (string) $row->alias : null,
            (int) $row->room_id,
            $members,
            [],
            (int) $row->owner_id,
            property_exists($row, 'background') ? (string) $row->background : 'bg_colour_08',
            property_exists($row, 'group_type') ? (int) $row->group_type : 0,
            property_exists($row, 'forum_type') ? (int) $row->forum_type : 0,
            property_exists($row, 'forum_premission') ? (int) $row->forum_premission : (property_exists($row, 'forum_permission') ? (int) $row->forum_permission : 0),
        );
    }

    private function topic(int $groupId, int $topicId): ?LegacyDiscussionTopic
    {
        $row = DB::table('cms_forum_threads')
            ->leftJoin('users as creator', 'creator.id', '=', 'cms_forum_threads.poster_id')
            ->leftJoin('cms_forum_replies as last_reply', function ($join): void {
                $join->on('last_reply.thread_id', '=', 'cms_forum_threads.id')
                    ->whereRaw('last_reply.id = (select max(id) from cms_forum_replies where thread_id = cms_forum_threads.id)');
            })
            ->leftJoin('users as last_user', 'last_user.id', '=', 'last_reply.poster_id')
            ->where('cms_forum_threads.group_id', $groupId)
            ->where('cms_forum_threads.id', $topicId)
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

        if (! $row) {
            return null;
        }

        $replyCount = max(1, (int) $row->reply_count);
        $replyPages = max(1, (int) ceil($replyCount / $this->repliesPerPage()));

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

    private function reply(int $topicId, int $replyId): ?LegacyDiscussionReply
    {
        $row = DB::table('cms_forum_replies')
            ->leftJoin('users', 'users.id', '=', 'cms_forum_replies.poster_id')
            ->where('cms_forum_replies.thread_id', $topicId)
            ->where('cms_forum_replies.id', $replyId)
            ->first([
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
            ]);

        return $row ? $this->replyFromRow($row) : null;
    }

    /** @return list<LegacyDiscussionReply> */
    private function replies(int $topicId, int $pageNumber, int $perPage): array
    {
        return DB::table('cms_forum_replies')
            ->leftJoin('users', 'users.id', '=', 'cms_forum_replies.poster_id')
            ->where('cms_forum_replies.thread_id', $topicId)
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
            ->map(fn (object $row): LegacyDiscussionReply => $this->replyFromRow($row))
            ->all();
    }

    private function replyFromRow(object $row): LegacyDiscussionReply
    {
        return new LegacyDiscussionReply(
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
            DB::table('cms_forum_replies')->where('poster_id', (int) $row->poster_id)->count(),
        );
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

    private function repliesPerPage(): int
    {
        return max(1, app(HavanaConfig::class)->integer('discussions.replies.per.page') ?: 20);
    }

    /** @return array<string, mixed> */
    private function previewContext(User $user): array
    {
        $badges = $this->displayBadges((int) $user->id);
        $now = Carbon::now();

        return [
            'playerDetails' => new LegacyUserData($user),
            'previewDay' => $now->format('M d, Y'),
            'previewTime' => $now->format('g:i A'),
            'userReplies' => DB::table('cms_forum_replies')->where('poster_id', (int) $user->id)->count(),
            'hasBadge' => $badges['badge'] !== '',
            'badge' => $badges['badge'],
            'hasGroup' => $badges['groupBadge'] !== '',
            'groupId' => (int) $user->favourite_group,
            'groupBadge' => $badges['groupBadge'],
        ];
    }

    /** @return array{badge: string, groupBadge: string} */
    private function displayBadges(int $userId): array
    {
        $badge = (string) (DB::table('users_badges')
            ->where('user_id', $userId)
            ->where('equipped', true)
            ->orderBy('slot_id')
            ->value('badge') ?? '');
        $groupBadge = (string) (DB::table('users')
            ->leftJoin('groups_details', 'groups_details.id', '=', 'users.favourite_group')
            ->where('users.id', $userId)
            ->value('groups_details.badge') ?? '');

        return ['badge' => $badge, 'groupBadge' => $groupBadge];
    }

    private function formatMessage(string $message): string
    {
        return nl2br(e($message), false);
    }

    private function groupLink(object $group): string
    {
        $alias = (string) ($group->alias ?? '');

        return $alias !== '' ? '/groups/'.$alias : '/groups/'.(int) $group->id.'/id';
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
}
