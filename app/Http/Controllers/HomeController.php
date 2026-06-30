<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyGroup;
use App\Support\LegacyHome;
use App\Support\LegacyInventoryWidget;
use App\Support\LegacyStickerProduct;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function show(Request $request, LegacyTemplate $template, string $user): Response
    {
        $owner = $this->resolveOwner($request, $user);

        if (! $owner || ! (bool) $owner->profile_visible) {
            abort(404);
        }

        $viewer = $this->currentUser($request);
        $editMode = $viewer instanceof User
            && (int) $viewer->id === (int) $owner->id
            && $this->hasEditSession((int) $owner->id);

        if ($editMode) {
            $request->session()->forget('groupEditSession');
            $request->session()->put('homeEditSession', (int) $owner->id);
        }

        $request->session()->put('page', 'me');

        if ($viewer instanceof User && (int) $viewer->id === (int) $owner->id) {
            DB::table('users_statistics')
                ->where('user_id', (int) $owner->id)
                ->update(['guestbook_unread_messages' => 0]);
        }

        return response($template->render('home', [
            'user' => $owner,
            'playerDetails' => $viewer ? new LegacyUserData($viewer) : null,
            'tags' => $this->userTags((int) $owner->id),
            'hasBadge' => false,
            'badgeCode' => '',
            'editMode' => $editMode,
            'stickers' => $this->placedHomeWidgets((int) $owner->id),
            'homeBannerAd' => 'hc_habbohome_banner_holo.png',
            'home' => new LegacyHome($this->homeBackground((int) $owner->id)),
            'canAddFriend' => $this->canAddFriend($viewer, $owner),
            'guestbookSetting' => 'public',
            'stickerLimit' => (int) ((int) $owner->club_expiration > time() ? 200 : 100),
            'tagCloud' => [],
            'hasFavouriteGroup' => $this->favouriteGroup((int) $owner->favourite_group) !== null,
            'group' => $this->favouriteGroup((int) $owner->favourite_group),
        ]));
    }

    public function startEditingSession(Request $request, string $user): RedirectResponse
    {
        $viewer = $this->currentUser($request);

        if (! $viewer) {
            return redirect('/');
        }

        if (! ctype_digit($user) || (int) $user !== (int) $viewer->id) {
            return redirect('/me');
        }

        $this->ensureHome((int) $viewer->id);
        DB::table('homes_edit_sessions')->where('user_id', (int) $viewer->id)->delete();
        DB::table('homes_edit_sessions')->insert([
            'user_id' => (int) $viewer->id,
            'expire' => time() + 900,
        ]);

        $request->session()->put('homeEditSession', (int) $viewer->id);
        $request->session()->forget('groupEditSession');

        return redirect('/home/'.$viewer->username);
    }

    public function cancelEditingSession(Request $request, string $user): RedirectResponse
    {
        $viewer = $this->currentUser($request);

        if (! $viewer) {
            return redirect('/');
        }

        if (! ctype_digit($user) || (int) $user !== (int) $viewer->id) {
            return redirect('/me');
        }

        DB::table('homes_edit_sessions')->where('user_id', (int) $viewer->id)->delete();
        $request->session()->forget(['homeEditSession', 'groupEditSession']);

        return redirect('/home/'.$viewer->username);
    }

    public function save(Request $request): Response|RedirectResponse
    {
        $viewer = $this->currentUser($request);

        if (! $viewer) {
            return redirect('/');
        }

        if (! $this->hasEditSession((int) $viewer->id)) {
            return response('');
        }

        $this->ensureHome((int) $viewer->id);

        if ($request->filled('background')) {
            $background = preg_replace('/[^a-zA-Z0-9_:-]/', '', (string) $request->input('background'));
            $background = explode(':', (string) $background)[0] ?: 'bg_pattern_abstract2';
            DB::table('homes_details')
                ->where('user_id', (int) $viewer->id)
                ->update(['background' => $background]);
        }

        DB::table('homes_edit_sessions')->where('user_id', (int) $viewer->id)->delete();
        $request->session()->forget('homeEditSession');

        return response('<script language="JavaScript" type="text/javascript">'."\n".
            "waitAndGo('/home/".$viewer->username."');\n".
            "</script>\n");
    }

    public function tagList(Request $request, LegacyTemplate $template): Response
    {
        $viewer = $this->currentUser($request);

        if (! $viewer) {
            return response('');
        }

        $accountId = (int) $request->input('accountId', 0);
        $owner = User::query()->find($accountId);

        if (! $owner) {
            return response('');
        }

        return response($template->render('homes/widget/habblet/taglist', [
            'tags' => $this->userTags($accountId),
            'user' => $owner,
            'playerDetails' => new LegacyUserData($viewer),
        ]));
    }

    private function resolveOwner(Request $request, string $value): ?User
    {
        if ($request->path() === 'home') {
            return null;
        }

        if ($request->is('home/*/id') && ctype_digit($value)) {
            return User::query()->find((int) $value);
        }

        return User::query()->where('username', $value)->first();
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

    private function ensureHome(int $userId): void
    {
        if (! DB::table('homes_details')->where('user_id', $userId)->exists()) {
            DB::table('homes_details')->insert([
                'user_id' => $userId,
                'background' => 'bg_pattern_abstract2',
            ]);
        }
    }

    private function homeBackground(int $userId): string
    {
        $background = DB::table('homes_details')->where('user_id', $userId)->value('background');

        return $background !== null ? (string) $background : 'bg_pattern_abstract2';
    }

    /** @return list<string> */
    private function userTags(int $userId): array
    {
        return DB::table('users_tags')
            ->where('user_id', $userId)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->orderBy('created_at')
            ->pluck('tag')
            ->map(fn ($tag): string => (string) $tag)
            ->all();
    }

    private function canAddFriend(?User $viewer, User $owner): bool
    {
        if (! $viewer || (int) $viewer->id === (int) $owner->id) {
            return false;
        }

        return ! DB::table('messenger_friends')
            ->where(function ($query) use ($viewer, $owner): void {
                $query->where('from_id', (int) $viewer->id)->where('to_id', (int) $owner->id);
            })
            ->orWhere(function ($query) use ($viewer, $owner): void {
                $query->where('from_id', (int) $owner->id)->where('to_id', (int) $viewer->id);
            })
            ->exists();
    }

    private function favouriteGroup(int $groupId): ?LegacyGroup
    {
        if ($groupId <= 0) {
            return null;
        }

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
        );
    }

    /** @return list<LegacyInventoryWidget> */
    private function placedHomeWidgets(int $userId): array
    {
        return DB::table('cms_stickers')
            ->where('user_id', $userId)
            ->where('group_id', 0)
            ->where('is_placed', true)
            ->get()
            ->map(fn (object $row): LegacyInventoryWidget => new LegacyInventoryWidget($row, $this->productFor((int) $row->sticker_id)))
            ->all();
    }

    private function productFor(int $stickerId): LegacyStickerProduct
    {
        $row = DB::table('cms_stickers_catalogue')->where('id', $stickerId)->first();

        return LegacyStickerProduct::fromRow($row, $stickerId);
    }

    private function hasEditSession(int $userId): bool
    {
        return DB::table('homes_edit_sessions')
            ->where('user_id', $userId)
            ->where('expire', '>', time())
            ->exists();
    }
}
