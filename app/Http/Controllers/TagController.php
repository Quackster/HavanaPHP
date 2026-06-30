<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LegacyTemplate;
use App\Support\LegacyKeyValue;
use App\Support\LegacyTagResult;
use App\Support\LegacyUserData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    public function index(Request $request, LegacyTemplate $template): Response
    {
        return $this->renderSearch($request, $template, (string) $request->query('tag', ''), 'tag');
    }

    public function search(Request $request, LegacyTemplate $template, ?string $tag = null): Response
    {
        if ($tag === null || $tag === '') {
            $routeTag = $request->route('tag');
            $tag = is_string($routeTag) ? $routeTag : (string) $request->query('tag', '');
        }

        return $this->renderSearch($request, $template, $tag, 'tag');
    }

    public function ajaxSearch(Request $request, LegacyTemplate $template): Response
    {
        return $this->renderSearch($request, $template, (string) $request->input('tag', ''), 'base/tag_search');
    }

    private function renderSearch(Request $request, LegacyTemplate $template, string $tag, string $view): Response
    {
        $tag = trim(strip_tags(urldecode($tag)));
        $pageId = max(1, (int) $request->query('pageNumber', 1));
        $allResults = $tag === '' ? [] : $this->tagResults($tag);
        $pages = array_chunk($allResults, 5);

        if (! isset($pages[$pageId - 1])) {
            $pageId = 1;
        }

        $pageResults = $pages[$pageId - 1] ?? [];
        $codePage = $pageId - 1;

        $request->session()->put('page', 'community');

        return response($template->render($view, [
            'tagList' => $pageResults,
            'totalTagUsers' => $pages,
            'tag' => $tag,
            'pageId' => $pageId,
            'totalCount' => count($allResults),
            'tagCloud' => $this->tagCloud(10),
            'tagSearchAdd' => $this->canAddTag($tag) ? $tag : '',
            'lastPage' => count($allResults),
            'showOlder' => isset($pages[$codePage - 1]),
            'showOldest' => isset($pages[$codePage - 2]),
            'showNewer' => isset($pages[$codePage + 1]),
            'showNewest' => isset($pages[$codePage + 2]),
            'showLast' => isset($pages[$codePage + 3]),
            'showLastPage' => count($pages),
            'showFirst' => isset($pages[$codePage - 3]),
            'showFirstPage' => 1,
        ]));
    }

    /** @return list<LegacyTagResult> */
    private function tagResults(string $tag): array
    {
        $userIds = DB::table('users_tags')
            ->where('tag', $tag)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');
        $tagsByUser = DB::table('users_tags')
            ->whereIn('user_id', $userIds)
            ->where('room_id', '0')
            ->where('group_id', '0')
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id');

        return $userIds
            ->map(function ($userId) use ($users, $tagsByUser): ?LegacyTagResult {
                $user = $users->get((int) $userId);

                if (! $user instanceof User) {
                    return null;
                }

                $tags = ($tagsByUser->get((int) $userId) ?? collect())
                    ->pluck('tag')
                    ->map(fn ($tag): string => (string) $tag)
                    ->all();

                return new LegacyTagResult((int) $userId, new LegacyUserData($user), $tags);
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<LegacyKeyValue> */
    public function tagCloud(int $limit): array
    {
        return DB::table('users_tags')
            ->select('tag', DB::raw('count(*) as total'))
            ->groupBy('tag')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): LegacyKeyValue => new LegacyKeyValue((string) $row->tag, 10 + min(10, (int) $row->total)))
            ->all();
    }

    private function canAddTag(string $tag): bool
    {
        return Auth::check() && preg_match('/^[a-z0-9]{1,20}$/', $tag) === 1;
    }
}
