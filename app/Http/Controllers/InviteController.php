<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HavanaConfig;
use App\Services\LegacyTemplate;
use App\Support\LegacyMessenger;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InviteController extends Controller
{
    public function inviteLink(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        return response($template->render('habblet/invite_referralLink'));
    }

    public function searchContent(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $searchString = (string) $request->input('searchString', '');
        $pageId = max(1, (int) $request->input('pageNumber', 1));
        $results = User::query()
            ->where('id', '!=', $user->id)
            ->whereRaw('LOWER(username) LIKE ?', [strtolower($searchString).'%'])
            ->limit(30)
            ->get()
            ->sortBy(fn (User $user): string => strtolower((string) $user->username))
            ->values()
            ->map(fn (User $user): LegacyUserData => new LegacyUserData($user))
            ->all();
        $pages = array_chunk($results, 5);
        $pageIndex = $pageId - 1;

        return response($template->render('habblet/invite_searchContent', [
            'searchResults' => $pages[$pageIndex] ?? [],
            'currentPage' => $pageId,
            'totalPages' => count($pages),
            'previousPageId' => isset($pages[$pageIndex - 1]) ? $pageId - 1 : -1,
            'nextPageId' => isset($pages[$pageIndex + 1]) ? $pageId + 1 : -1,
            'messenger' => new LegacyMessenger($user->id),
        ]));
    }

    public function confirmAddFriend(Request $request, LegacyTemplate $template): Response|RedirectResponse
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('habblet/invite_confirmAddFriend', [
            'username' => (string) $user->username,
        ]));
    }

    public function addFriend(Request $request, LegacyTemplate $template, HavanaConfig $config): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $accountId = $this->integerInput($request, 'accountId');

        return response($template->render('habblet/invite_addFriend', [
            'message' => $this->createFriendRequestResponse($request, $config, $accountId),
        ]));
    }

    public function add(Request $request, HavanaConfig $config): Response|RedirectResponse
    {
        if (! $this->currentUser($request)) {
            return redirect('/');
        }

        $message = $this->createFriendRequestResponse($request, $config, $this->integerInput($request, 'accountId'));

        return response('Dialog.showInfoDialog("add-friend-messages", "'.addslashes($message).'", "OK");')
            ->header('Content-Type', 'application/x-javascript');
    }

    private function createFriendRequestResponse(Request $request, HavanaConfig $config, ?int $accountId): string
    {
        $user = $this->currentUser($request);
        $target = $accountId !== null ? User::query()->find($accountId) : null;

        if (! $user || ! $target instanceof User || strcasecmp((string) $target->username, 'Abigail.Ryan') === 0) {
            return 'There was an error finding the user for the friend request.';
        }

        $maxFriends = $config->integer('messenger.max.friends') ?: 300;

        if ($this->friendCount($user->id) >= $maxFriends) {
            return 'Your friends list is full.';
        }

        if ($this->hasFriend($target->id, $user->id)) {
            return 'This person is already your friend';
        }

        if ($this->hasRequest($user->id, $target->id)) {
            return 'There is already a friend request for this user.';
        }

        if ($this->friendCount($target->id) >= $maxFriends) {
            return "This user's friend list is full.";
        }

        if (! (bool) $target->allow_friend_requests) {
            return 'This user does not accept friend requests at the moment.';
        }

        if ($accountId === (int) $user->id) {
            return 'There was an error processing your request.';
        }

        DB::table('messenger_requests')->insert([
            'from_id' => $user->id,
            'to_id' => $target->id,
        ]);

        return 'Friend request has been sent successfully.';
    }

    private function hasFriend(int $targetId, int $userId): bool
    {
        return DB::table('messenger_friends')
            ->where('to_id', $targetId)
            ->where('from_id', $userId)
            ->exists();
    }

    private function hasRequest(int $fromId, int $toId): bool
    {
        return DB::table('messenger_requests')
            ->where('from_id', $fromId)
            ->where('to_id', $toId)
            ->exists();
    }

    private function friendCount(int $userId): int
    {
        return DB::table('messenger_friends')->where('to_id', $userId)->count();
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
