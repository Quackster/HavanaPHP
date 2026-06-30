<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyFriendCategory;
use App\Support\LegacyMessengerFriend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FriendManagementController extends Controller
{
    public function editCategory(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $name = trim((string) $request->input('name', ''));
        $categoryId = (int) $request->input('categoryId', 0);

        if ($name !== '') {
            DB::table('messenger_categories')
                ->where('id', $categoryId)
                ->where('user_id', $user->id)
                ->update(['name' => $name]);
        }

        return $this->categoryWidget($template, $user);
    }

    public function createCategory(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $name = substr(trim((string) $request->input('name', '')), 0, 50);

        if ($name !== '') {
            DB::table('messenger_categories')->insert([
                'user_id' => $user->id,
                'name' => $name,
            ]);
        }

        return $this->categoryWidget($template, $user);
    }

    public function deleteCategory(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $categoryId = (int) $request->input('categoryId', 0);

        DB::table('messenger_categories')
            ->where('id', $categoryId)
            ->where('user_id', $user->id)
            ->delete();

        DB::table('messenger_friends')
            ->where('to_id', $user->id)
            ->where('category_id', $categoryId)
            ->update(['category_id' => 0]);

        return $this->categoryWidget($template, $user);
    }

    public function viewCategory(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $this->clearXssKey($request);

        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $pageSize = (int) $request->input('pageSize', $request->query('pageSize', 30));
        $pageNumber = (int) $request->query('pageNumber', 1);
        $categoryId = $request->has('categoryId') ? (int) $request->query('categoryId') : -1;
        $searchString = $request->input('searchString', $request->query('searchString'));

        if ($pageSize > 100 || $pageSize <= 0) {
            $pageSize = 30;
        }

        if ($pageNumber <= 0) {
            $pageNumber = 1;
        }

        return response($template->render('profile/profile_widgets/friend_view_category', $this->friendContext(
            $user,
            $pageSize,
            $pageNumber,
            $categoryId,
            is_string($searchString) && $searchString !== '' ? $searchString : null,
        )));
    }

    public function updateCategoryOptions(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        return response($template->render('profile/profile_widgets/friend_category_options', [
            'categories' => $this->categories($user->id),
        ]));
    }

    public function moveFriends(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $categoryId = $request->has('moveCategoryId') ? (int) $request->input('moveCategoryId') : -1;
        $friendIds = $this->friendIds($request);

        foreach ($friendIds as $friendId) {
            DB::table('messenger_friends')
                ->where('from_id', $friendId)
                ->where('to_id', $user->id)
                ->update(['category_id' => $categoryId]);
        }

        $pageSize = (int) $request->input('pageSize', 30);

        if ($pageSize > 100 || $pageSize <= 0) {
            $pageSize = 30;
        }

        $this->clearXssKey($request);

        return response($template->render('profile/profile_widgets/friend_view_category', $this->friendContext(
            $user,
            $pageSize,
            1,
            $categoryId,
            null,
        )));
    }

    public function deleteFriends(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $friendIds = $this->friendIds($request);

        if ($request->has('friendId')) {
            $friendId = $this->integerInput($request, 'friendId');

            if ($friendId !== null) {
                $friendIds[] = $friendId;
            }
        }

        foreach (array_unique($friendIds) as $friendId) {
            DB::table('messenger_friends')
                ->where('from_id', $user->id)
                ->where('to_id', $friendId)
                ->delete();
            DB::table('messenger_friends')
                ->where('from_id', $friendId)
                ->where('to_id', $user->id)
                ->delete();
        }

        $this->clearXssKey($request);

        return response($template->render('profile/profile_widgets/friend_view_category', $this->friendContext(
            $user,
            30,
            1,
            -1,
            null,
        )));
    }

    private function categoryWidget(LegacyTemplate $template, User $user): Response
    {
        return response($template->render('profile/profile_widgets/friend_category_widget', [
            'categories' => $this->categories($user->id),
        ]));
    }

    /** @return array<string, mixed> */
    private function friendContext(User $user, int $limit, int $currentPage, int $categoryId, ?string $searchString): array
    {
        $categories = $this->categories($user->id);
        $friends = $this->friends($user->id, $limit, $currentPage, $categoryId, $searchString, $categories);
        $friendsCount = $this->friendsCount($user->id, $categoryId, $searchString);
        $pages = max(1, (int) ceil($friendsCount / $limit));

        return [
            'friends' => $friends,
            'categories' => $categories,
            'currentPage' => $currentPage,
            'pageLimit' => $limit,
            'firstPage' => $currentPage >= 2 ? 1 : -1,
            'previousPage' => $currentPage > 1 ? $currentPage - 1 : -1,
            'nextPage' => $pages >= ($currentPage + 1) ? $currentPage + 1 : -1,
            'lastPage' => $pages >= ($currentPage + 2) ? $pages : -1,
        ];
    }

    /** @return list<LegacyFriendCategory> */
    private function categories(int $userId): array
    {
        return DB::table('messenger_categories')
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row): LegacyFriendCategory => new LegacyFriendCategory(
                (int) $row->id,
                (string) $row->name,
            ))
            ->all();
    }

    /**
     * @param  list<LegacyFriendCategory>  $categories
     * @return list<LegacyMessengerFriend>
     */
    private function friends(int $userId, int $limit, int $page, int $categoryId, ?string $searchString, array $categories): array
    {
        $query = DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', $userId);

        if ($searchString !== null) {
            $query->where('users.username', 'like', $searchString.'%');
        }

        $validCategoryIds = collect($categories)
            ->map(fn (LegacyFriendCategory $category): int => $category->getId())
            ->all();

        return $query
            ->orderByDesc('users.last_online')
            ->forPage($page, $limit)
            ->get([
                'users.id',
                'users.username',
                'users.last_online',
                'messenger_friends.category_id',
            ])
            ->map(function (object $row) use ($userId, $validCategoryIds): object {
                if ((int) $row->category_id !== 0 && ! in_array((int) $row->category_id, $validCategoryIds, true)) {
                    DB::table('messenger_friends')
                        ->where('from_id', (int) $row->id)
                        ->where('to_id', $userId)
                        ->update(['category_id' => 0]);

                    $row->category_id = 0;
                }

                return $row;
            })
            ->filter(fn (object $row): bool => $categoryId <= -1 || (int) $row->category_id === $categoryId)
            ->map(fn ($row): LegacyMessengerFriend => new LegacyMessengerFriend(
                (int) $row->id,
                (string) $row->username,
                (int) $row->category_id,
                $row->last_online,
            ))
            ->all();
    }

    private function friendsCount(int $userId, int $categoryId, ?string $searchString): int
    {
        $query = DB::table('messenger_friends')
            ->join('users', 'messenger_friends.from_id', '=', 'users.id')
            ->where('messenger_friends.to_id', $userId);

        if ($searchString !== null) {
            $query->where('users.username', 'like', $searchString.'%');
        }

        return $query->count();
    }

    /** @return list<int> */
    private function friendIds(Request $request): array
    {
        $value = $request->input('friendList', $request->input('friendList', []));

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->map(fn ($id): ?int => is_string($id) || is_int($id) ? $this->parseInteger($id) : null)
            ->filter(fn (?int $id): bool => $id !== null && $id > 0)
            ->values()
            ->all();
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

    private function clearXssKey(Request $request): void
    {
        $request->session()->forget(['xssKey', 'xssSeed', 'xssRequested']);
    }

    private function integerInput(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        return is_string($value) || is_int($value) ? $this->parseInteger($value) : null;
    }

    private function parseInteger(string|int $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }
}
