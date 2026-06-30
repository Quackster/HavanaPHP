<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
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

class GroupActionController extends Controller
{
    public function groupCreateForm(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $groupCost = max(0, app(HavanaConfig::class)->integer('group.purchase.cost', 10));

        if ((int) $user->credits < $groupCost) {
            return response($template->render('groups/habblet/purchase_result_error', [
                'playerDetails' => new LegacyUserData($user),
            ]));
        }

        return response($template->render('groups/habblet/group_create_form', [
            'groupCost' => $groupCost,
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function purchaseConfirmation(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        return response($template->render('groups/habblet/purchase_confirmation', [
            'groupName' => $this->cleanText((string) $request->input('name', $request->input('group_name', '')), 30),
            'groupCost' => max(0, app(HavanaConfig::class)->integer('group.purchase.cost', 10)),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function purchaseAjax(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return response('');
        }

        $groupCost = max(0, app(HavanaConfig::class)->integer('group.purchase.cost', 10));

        if ((int) $user->credits < $groupCost) {
            return response('');
        }

        $name = $this->cleanText((string) $request->input('name', $request->input('group_name', '')), 30);
        $description = $this->cleanText((string) $request->input('description', $request->input('group_description', '')), 255);

        if ($name === '') {
            $name = $user->username."'s group";
        }

        $groupId = DB::table('groups_details')->insertGetId([
            'name' => $name,
            'description' => $description,
            'owner_id' => (int) $user->id,
            'room_id' => 0,
            'badge' => 'b0503Xs09114s05013s05015',
            'recommended' => 0,
            'background' => 'bg_colour_08',
            'views' => 0,
            'topics' => 0,
            'group_type' => 0,
            'forum_type' => 0,
            'forum_premission' => 0,
            'alias' => null,
            'created_at' => now(),
        ]);

        DB::table('groups_memberships')->insert([
            'user_id' => (int) $user->id,
            'group_id' => $groupId,
            'member_rank' => '3',
            'is_pending' => false,
            'created_at' => now(),
        ]);

        $user->forceFill(['credits' => max(0, (int) $user->credits - $groupCost)])->save();

        return response($template->render('groups/habblet/purchase_ajax', [
            'groupName' => $name,
            'groupId' => $groupId,
            'deductedCredits' => (int) $user->credits,
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function startEditingSession(Request $request, string $group): RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupDto = ctype_digit($group) ? $this->groupById((int) $group) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $groupDto) {
            abort(404);
        }

        if ($groupDto->hasAdministrator((int) $user->id)) {
            DB::table('groups_edit_sessions')->where('user_id', (int) $user->id)->where('group_id', $groupDto->id)->delete();
            DB::table('groups_edit_sessions')->insert([
                'user_id' => (int) $user->id,
                'group_id' => $groupDto->id,
                'expire' => time() + 900,
            ]);
            $request->session()->forget('homeEditSession');
            $request->session()->put('groupEditSession', $groupDto->id);
        }

        return redirect($groupDto->generateClickLink());
    }

    public function cancelEditingSession(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user || ! $request->session()->has('groupEditSession')) {
            return redirect('/');
        }

        $groupId = (int) $request->session()->get('groupEditSession');
        DB::table('groups_edit_sessions')->where('user_id', (int) $user->id)->where('group_id', $groupId)->delete();
        $request->session()->forget(['homeEditSession', 'groupEditSession']);
        $group = $this->groupById($groupId);

        return redirect($group?->generateClickLink() ?? '/');
    }

    public function saveEditingSession(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = (int) $request->session()->get('groupEditSession', 0);
        $group = $this->groupById($groupId);

        if (! $user) {
            return redirect('/');
        }

        if (! $group || ! $group->hasAdministrator((int) $user->id) || ! $this->hasEditSession((int) $user->id, $group->id)) {
            return response('');
        }

        if ($request->filled('background')) {
            $background = preg_replace('/[^a-zA-Z0-9_:-]/', '', (string) $request->input('background'));
            $background = explode(':', (string) $background)[0] ?: 'bg_colour_08';
            DB::table('groups_details')->where('id', $group->id)->update(['background' => $background]);
        }

        return response('');
    }

    public function groupSettings(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        return response($template->render('groups/habblet/group_settings', [
            'group' => $group,
            'selected'.$group->getGroupType().'GroupType' => ' checked="checked"',
            'selected'.$group->getForumType()->getId().'ForumType' => ' checked="checked"',
            'selected'.$group->getForumPermission()->getId().'ForumPermissionType' => ' checked="checked"',
            'charactersLeft' => (string) max(0, 255 - strlen($group->getDescription())),
            'rooms' => $this->ownedRooms((int) $user->id, $group->id),
        ]));
    }

    public function checkGroupUrl(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/habblet/check_group_url', [
            'url' => e((string) $request->input('url', '')),
        ]));
    }

    public function updateGroupSettings(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        $name = $this->cleanText((string) $request->input('name', $request->input('group_name', '')), 30);
        $description = $this->cleanText((string) $request->input('description', $request->input('group_description', '')), 255);
        $alias = substr((string) preg_replace('/[^a-zA-Z0-9]/', '', (string) $request->input('url', $request->input('group_url', ''))), 0, 30);
        $groupType = min(3, max(0, (int) $request->input('type', $request->input('group_type', 0))));
        $forumType = min(1, max(0, (int) $request->input('forumType', $request->input('forum_type', 0))));
        $forumPermission = min(2, max(0, (int) $request->input('newTopicPermission', $request->input('new_topic_permission', 0))));
        $roomId = max(0, (int) $request->input('roomId', 0));

        if ($roomId > 0 && ! DB::table('rooms')->where('id', $roomId)->where('owner_id', (string) $user->id)->exists()) {
            $roomId = 0;
        }

        DB::table('rooms')->where('group_id', $group->id)->update(['group_id' => 0]);

        if ($roomId > 0) {
            DB::table('rooms')->where('id', $roomId)->update(['group_id' => $group->id]);
        }

        $updates = [
            'name' => $name,
            'description' => $description,
            'forum_type' => $forumType,
            'forum_premission' => $forumPermission,
            'room_id' => $roomId,
        ];

        if ($group->getGroupType() !== 3) {
            $updates['group_type'] = $groupType;
        }

        if ($group->getAlias() === '' && $alias !== '' && ! DB::table('groups_details')->where('alias', $alias)->where('id', '!=', $group->id)->exists()) {
            $updates['alias'] = $alias;
        }

        DB::table('groups_details')->where('id', $group->id)->update($updates);
        $updatedGroup = $this->groupById($group->id);

        return response($template->render('groups/habblet/update_group_settings', [
            'group' => $updatedGroup ?? $group,
            'message' => 'Editing group settings successful',
        ]));
    }

    public function showBadgeEditor(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        return response($template->render('groups/habblet/show_badge_editor', ['group' => $group]));
    }

    public function updateGroupBadge(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        $badge = preg_replace('/[^a-zA-Z0-9]/', '', (string) $request->input('code', ''));
        DB::table('groups_details')->where('id', $group->id)->update(['badge' => $badge]);

        return redirect($group->generateClickLink());
    }

    public function confirmDeleteGroup(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        return response($template->render('groups/habblet/confirm_delete_group', ['group' => $group]));
    }

    public function deleteGroup(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user) {
            return redirect('/');
        }

        if (! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        DB::table('groups_memberships')->where('group_id', $group->id)->delete();
        DB::table('users')->where('favourite_group', $group->id)->update(['favourite_group' => 0]);
        DB::table('users_tags')->where('group_id', (string) $group->id)->delete();
        DB::table('rooms')->where('group_id', $group->id)->update(['group_id' => 0]);

        if (Schema::hasTable('groups_edit_sessions')) {
            DB::table('groups_edit_sessions')->where('group_id', $group->id)->delete();
        }

        DB::table('groups_details')->where('id', $group->id)->delete();

        return response($template->render('groups/habblet/delete_group'));
    }

    public function join(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $group || $group->isMember((int) $user->id) || $group->isPendingMember((int) $user->id) || $group->getGroupType() === 2) {
            return response('');
        }

        $isPending = $group->getGroupType() === 1 && (int) $user->rank < 5;
        DB::table('groups_memberships')->insert([
            'user_id' => (int) $user->id,
            'group_id' => $group->id,
            'member_rank' => '1',
            'is_pending' => $isPending,
            'created_at' => now(),
        ]);

        return response($template->render($isPending ? 'groups/member/member_added_request' : 'groups/member/member_added'));
    }

    public function confirmLeave(LegacyTemplate $template): Response
    {
        return response($template->render('groups/member/confirm_leave'));
    }

    public function leave(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $group) {
            return response('');
        }

        if ($group->isMember((int) $user->id)) {
            DB::table('groups_memberships')
                ->where('group_id', $group->id)
                ->where('user_id', (int) $user->id)
                ->delete();

            if ((int) $user->favourite_group === $group->id) {
                $user->forceFill(['favourite_group' => 0])->save();
            }
        }

        return response($template->render('groups/member/leave', ['groupId' => $group->id]));
    }

    public function confirmSelectFavourite(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $groupName = DB::table('groups_details')
            ->where('id', $this->integerInput($request, 'groupId') ?? 0)
            ->value('name');

        return $groupName === null
            ? response('')
            : response($template->render('groups/favourite/confirm_select_favourite', ['groupName' => (string) $groupName]));
    }

    public function selectFavourite(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $group || ! $group->isMember((int) $user->id)) {
            return response('');
        }

        $user->forceFill(['favourite_group' => $group->id])->save();

        return response('OK');
    }

    public function confirmDeselectFavourite(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/favourite/confirm_deselect_favourite'));
    }

    public function deselectFavourite(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $group || ! $group->isMember((int) $user->id)) {
            return response('');
        }

        $user->forceFill(['favourite_group' => 0])->save();

        return response('OK');
    }

    public function addGroupTag(Request $request): Response
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user || ! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        $limit = max(1, app(HavanaConfig::class)->integer('max.tags.groups', 20));
        $tagCount = DB::table('users_tags')
            ->where('group_id', (string) $group->id)
            ->where('room_id', '0')
            ->count();

        if ($tagCount >= $limit) {
            return response('taglimit');
        }

        $tag = strtolower(trim(strip_tags((string) $request->input('tagName', ''))));

        if (preg_match('/^[a-z0-9]{1,20}$/', $tag) !== 1) {
            return response('invalidtag');
        }

        $exists = DB::table('users_tags')
            ->where('tag', $tag)
            ->where('user_id', 0)
            ->where('room_id', '0')
            ->where('group_id', (string) $group->id)
            ->exists();

        if (! $exists) {
            DB::table('users_tags')->insert([
                'user_id' => 0,
                'tag' => $tag,
                'room_id' => '0',
                'group_id' => (string) $group->id,
                'created_at' => now(),
            ]);
        }

        return response('valid');
    }

    public function removeGroupTag(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user || ! $group || $group->getOwnerId() !== (int) $user->id) {
            return response('');
        }

        DB::table('users_tags')
            ->where('group_id', (string) $group->id)
            ->where('room_id', '0')
            ->where('tag', (string) $request->input('tagName', ''))
            ->delete();

        return $this->renderGroupTags($request, $template, $group, $user);
    }

    public function listGroupTags(Request $request, LegacyTemplate $template): Response
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user || ! $group) {
            return response('');
        }

        return $this->renderGroupTags($request, $template, $group, $user);
    }

    public function memberList(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $group || ! $group->hasAdministrator((int) $user->id)) {
            return response('');
        }

        $pageNumber = max(1, (int) $request->input('pageNumber', 1));
        $isPending = filter_var($request->input('pending', false), FILTER_VALIDATE_BOOL);
        $limit = 12;
        $pendingMembers = DB::table('groups_memberships')->where('group_id', $group->id)->where('is_pending', true)->count();
        $groupMembers = DB::table('groups_memberships')->where('group_id', $group->id)->where('is_pending', false)->count();
        $memberCount = $isPending ? $pendingMembers : $groupMembers;
        $pages = max(1, (int) ceil($memberCount / $limit));
        $members = $this->memberRows($group->id, $isPending, $pageNumber, $limit);

        return response($template->render('groups/memberlist', [
            'pages' => $pages,
            'memberList' => $members,
            'currentPage' => $pageNumber,
            'selfMember' => new LegacyGroupMember($group->getMember((int) $user->id)->getRankId(), (int) $user->favourite_group, $user),
        ]))->header('X-JSON', json_encode([
            'pending' => 'Pending members ('.$pendingMembers.')',
            'members' => 'Members ('.$groupMembers.')',
        ]));
    }

    public function confirmRevokeRights(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/member/confirm_revoke_rights', [
            'targetIds' => count($this->targetIds($request)),
        ]));
    }

    public function revokeRights(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user || ! $group || $group->getMember((int) $user->id)->getRankId() !== 3) {
            return $user ? response('') : redirect('/');
        }

        foreach ($this->targetIds($request) as $memberId) {
            if ($group->getMember($memberId)->getRankId() !== 2) {
                continue;
            }

            DB::table('groups_memberships')
                ->where('group_id', $group->id)
                ->where('user_id', $memberId)
                ->where('member_rank', '2')
                ->where('is_pending', false)
                ->update(['member_rank' => '1']);
        }

        return response('OK');
    }

    public function confirmGiveRights(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/member/confirm_give_rights', [
            'targetIds' => count($this->targetIds($request)),
        ]));
    }

    public function giveRights(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;

        if (! $user || ! $group || $group->getMember((int) $user->id)->getRankId() !== 3) {
            return $user ? response('') : redirect('/');
        }

        foreach ($this->targetIds($request) as $memberId) {
            if ($group->getMember($memberId)->getRankId() !== 1) {
                continue;
            }

            DB::table('groups_memberships')
                ->where('group_id', $group->id)
                ->where('user_id', $memberId)
                ->where('member_rank', '1')
                ->where('is_pending', false)
                ->update(['member_rank' => '2']);
        }

        return response('OK');
    }

    public function confirmRemove(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/member/confirm_remove', [
            'targetIds' => count($this->targetIds($request)),
        ]));
    }

    public function remove(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;
        $selfRank = $user && $group ? $group->getMember((int) $user->id)->getRankId() : 0;

        if (! $user || ! $group || $selfRank < 2) {
            return $user ? response('') : redirect('/');
        }

        foreach ($this->targetIds($request) as $memberId) {
            if ($group->getMember($memberId)->getRankId() !== 1) {
                continue;
            }

            DB::table('groups_memberships')
                ->where('group_id', $group->id)
                ->where('user_id', $memberId)
                ->where('member_rank', '1')
                ->where('is_pending', false)
                ->delete();

            User::query()
                ->whereKey($memberId)
                ->where('favourite_group', $group->id)
                ->update(['favourite_group' => 0]);
        }

        return response('OK');
    }

    public function confirmAccept(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $groupName = DB::table('groups_details')
            ->where('id', $this->integerInput($request, 'groupId') ?? 0)
            ->value('name');

        return $groupName === null
            ? response('')
            : response($template->render('groups/member/confirm_accept', ['groupName' => (string) $groupName]));
    }

    public function accept(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;
        $selfRank = $user && $group ? $group->getMember((int) $user->id)->getRankId() : 0;

        if (! $user || ! $group || $selfRank < 2) {
            return $user ? response('') : redirect('/');
        }

        foreach ($this->targetIds($request) as $memberId) {
            DB::table('groups_memberships')
                ->where('group_id', $group->id)
                ->where('user_id', $memberId)
                ->where('is_pending', true)
                ->update([
                    'member_rank' => '1',
                    'is_pending' => false,
                ]);
        }

        return response('OK');
    }

    public function confirmDecline(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('groups/member/confirm_decline', [
            'targetIds' => count($this->targetIds($request)),
        ]));
    }

    public function decline(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);
        $groupId = $this->integerInput($request, 'groupId');
        $group = $groupId !== null ? $this->groupById($groupId) : null;
        $selfRank = $user && $group ? $group->getMember((int) $user->id)->getRankId() : 0;

        if (! $user || ! $group || $selfRank < 2) {
            return $user ? response('') : redirect('/');
        }

        foreach ($this->targetIds($request) as $memberId) {
            DB::table('groups_memberships')
                ->where('group_id', $group->id)
                ->where('user_id', $memberId)
                ->where('is_pending', true)
                ->delete();
        }

        return response('OK');
    }

    private function renderGroupTags(Request $request, LegacyTemplate $template, LegacyGroup $group, User $user): Response
    {
        $tags = DB::table('users_tags')
            ->where('room_id', '0')
            ->where('group_id', (string) $group->id)
            ->orderBy('created_at')
            ->pluck('tag')
            ->map(fn ($tag): string => (string) $tag)
            ->all();

        return response($template->render('groups/habblet/listgrouptags', [
            'tags' => $tags,
            'group' => $group,
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    /** @return list<LegacyGroupMember> */
    private function memberRows(int $groupId, bool $isPending, int $pageNumber, int $limit): array
    {
        return DB::table('groups_memberships')
            ->join('users', 'users.id', '=', 'groups_memberships.user_id')
            ->where('groups_memberships.group_id', $groupId)
            ->where('groups_memberships.is_pending', $isPending)
            ->orderByDesc('groups_memberships.member_rank')
            ->orderBy('users.username')
            ->offset(($pageNumber - 1) * $limit)
            ->limit($limit)
            ->get([
                'groups_memberships.member_rank',
                'users.id',
                'users.username',
                'users.password',
                'users.figure',
                'users.pool_figure',
                'users.sex',
                'users.motto',
                'users.email',
                'users.credits',
                'users.pixels',
                'users.tickets',
                'users.film',
                'users.rank',
                'users.last_online',
                'users.remember_token',
                'users.is_online',
                'users.created_at',
                'users.updated_at',
                'users.sso_ticket',
                'users.machine_id',
                'users.club_subscribed',
                'users.club_expiration',
                'users.club_gift_due',
                'users.allow_stalking',
                'users.allow_friend_requests',
                'users.online_status_visible',
                'users.profile_visible',
                'users.wordfilter_enabled',
                'users.trade_enabled',
                'users.trade_ban_expiration',
                'users.sound_enabled',
                'users.selected_room_id',
                'users.tutorial_finished',
                'users.daily_coins_enabled',
                'users.daily_respect_points',
                'users.respect_points',
                'users.respect_day',
                'users.respect_given',
                'users.totem_effect_expiry',
                'users.favourite_group',
                'users.home_room',
                'users.has_flash_warning',
            ])
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

    /** @return list<LegacyRoom> */
    private function ownedRooms(int $userId, int $groupId): array
    {
        $ownerName = (string) DB::table('users')->where('id', $userId)->value('username');

        return DB::table('rooms')
            ->where('owner_id', (string) $userId)
            ->where(function ($query) use ($groupId): void {
                $query->where('group_id', 0)->orWhere('group_id', $groupId);
            })
            ->orderBy('name')
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

    private function hasEditSession(int $userId, int $groupId): bool
    {
        return Schema::hasTable('groups_edit_sessions')
            && DB::table('groups_edit_sessions')
                ->where('user_id', $userId)
                ->where('group_id', $groupId)
                ->where('expire', '>', time())
                ->exists();
    }

    private function cleanText(string $value, int $limit): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $limit);
    }

    /** @return list<int> */
    private function targetIds(Request $request): array
    {
        $targetIds = $request->input('targetIds', []);

        if (is_string($targetIds)) {
            $targetIds = explode(',', $targetIds);
        }

        if (! is_array($targetIds)) {
            return [];
        }

        return collect($targetIds)
            ->map(fn ($targetId): int => ctype_digit((string) $targetId) ? (int) $targetId : 0)
            ->filter(fn (int $targetId): bool => $targetId > 0)
            ->unique()
            ->values()
            ->all();
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

    private function groupById(int $id): ?LegacyGroup
    {
        $row = DB::table('groups_details')->where('id', $id)->first();

        if (! $row) {
            return null;
        }

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
            property_exists($row, 'forum_premission') ? (int) $row->forum_premission : 0,
        );
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
