<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyKeyValue;
use App\Support\LegacyNoteWidget;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NoteEditorController extends Controller
{
    private const STICKIE_NOTE_ID = 13;

    public function editor(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $skin = (int) $request->input('skin', 1);
        $context = [
            'noteText' => mb_substr((string) $request->input('noteText', ''), 0, 500),
            'playerDetails' => new LegacyUserData($user),
        ];

        if ($skin > 0 && $skin < 9) {
            $context['skin'.$skin.'Selected'] = ' selected';
        }

        return response($template->render('homes/editor/noteeditor', $context));
    }

    public function preview(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('homes/editor/preview', [
            'skin' => LegacyNoteWidget::skinName((int) $request->input('skin', 1)),
            'noteText' => LegacyNoteWidget::formatPreviewText((string) $request->input('noteText', '')),
            'playerDetails' => new LegacyUserData($user),
        ]));
    }

    public function search(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $query = trim((string) $request->query('query', ''));
        $scope = (int) $request->query('scope', 3);
        $type = 'group';
        $results = [];

        if ($scope === 1) {
            $type = 'habbo';
            $results = User::query()
                ->where('username', 'like', $query.'%')
                ->orderBy('username')
                ->limit(10)
                ->get(['id', 'username'])
                ->map(fn (User $user): LegacyKeyValue => new LegacyKeyValue((string) $user->username, (int) $user->id))
                ->all();
        } elseif ($scope === 2) {
            $type = 'room';
            $results = DB::table('rooms')
                ->where('name', 'like', $query.'%')
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name'])
                ->map(fn (object $row): LegacyKeyValue => new LegacyKeyValue((string) $row->name, (int) $row->id))
                ->all();
        } else {
            $results = DB::table('groups_details')
                ->where('name', 'like', $query.'%')
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name'])
                ->map(fn (object $row): LegacyKeyValue => new LegacyKeyValue((string) $row->name, (int) $row->id))
                ->all();
        }

        return response($template->render('homes/editor/search', [
            'querySearch' => $results,
            'type' => $type,
        ]));
    }

    public function place(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $row = DB::table('cms_stickers')
            ->where('user_id', (int) $user->id)
            ->where('group_id', 0)
            ->where('is_placed', false)
            ->where('sticker_id', self::STICKIE_NOTE_ID)
            ->orderBy('id')
            ->first();

        if (! $row) {
            return response('');
        }

        $skin = $this->allowedSkin((int) $request->input('skin', 1), $user);
        DB::table('cms_stickers')->where('id', (int) $row->id)->update([
            'x' => '20',
            'y' => '30',
            'z' => '1',
            'skin_id' => $skin,
            'group_id' => $groupId ?? 0,
            'text' => mb_substr((string) $request->input('noteText', ''), 0, 500),
            'is_placed' => true,
        ]);
        $row = DB::table('cms_stickers')->where('id', (int) $row->id)->first();

        return response($template->render('homes/widget/note', [
            'sticker' => new LegacyNoteWidget($row),
            'editMode' => true,
            'playerDetails' => new LegacyUserData($user),
        ]))->header('X-JSON', (string) $row->id);
    }

    public function stickieEdit(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $stickieId = $this->integerInput($request, 'stickieId');
        $query = $stickieId !== null ? DB::table('cms_stickers')->where('id', $stickieId) : null;

        if (! $query) {
            return response('');
        }

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        } else {
            $query->where('user_id', (int) $user->id)->where('group_id', 0);
        }

        $row = $query->first();

        if (! $row) {
            return response('');
        }

        $skin = $this->allowedSkin((int) $request->input('skinId', 1), $user);
        DB::table('cms_stickers')->where('id', (int) $row->id)->update(['skin_id' => $skin]);
        $row = DB::table('cms_stickers')->where('id', (int) $row->id)->first();
        $json = '{"id":"'.$row->id.'","cssClass":"n_skin_'.LegacyNoteWidget::skinName($skin).'","type":"stickie"}';

        return response($template->render('homes/widget/note', [
            'sticker' => new LegacyNoteWidget($row),
            'editMode' => true,
            'playerDetails' => new LegacyUserData($user),
        ]))->header('X-JSON', $json);
    }

    public function stickieDelete(Request $request): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $groupId = $this->editableGroupId($request, (int) $user->id);

        if ($groupId === null && ! $this->hasHomeEditSession($request, (int) $user->id)) {
            return response('');
        }

        $stickieId = $this->integerInput($request, 'stickieId');
        $query = $stickieId !== null ? DB::table('cms_stickers')->where('id', $stickieId) : null;

        if (! $query) {
            return response('');
        }

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        } else {
            $query->where('user_id', (int) $user->id)->where('group_id', 0)->where('is_placed', true);
        }

        $query->delete();

        return response('SUCCESS');
    }

    private function allowedSkin(int $skinId, User $user): int
    {
        if (($skinId === 7 || $skinId === 8) && (int) $user->club_expiration <= time()) {
            return 1;
        }

        if ($skinId === 9 && (int) $user->rank < 5) {
            return 1;
        }

        return $skinId;
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

    private function editableGroupId(Request $request, int $userId): ?int
    {
        $groupId = (int) $request->session()->get('groupEditSession', 0);

        if ($groupId <= 0) {
            return null;
        }

        $hasSession = DB::table('groups_edit_sessions')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->where('expire', '>', time())
            ->exists();

        return $hasSession ? $groupId : null;
    }

    private function hasHomeEditSession(Request $request, int $userId): bool
    {
        return (int) $request->session()->get('homeEditSession', 0) === $userId
            && DB::table('homes_edit_sessions')
                ->where('user_id', $userId)
                ->where('expire', '>', time())
                ->exists();
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
