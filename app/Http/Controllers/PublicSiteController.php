<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CollectablesService;
use App\Services\LegacyTemplate;
use App\Support\LegacyClubGiftItem;
use App\Support\LegacyHighscoreEntry;
use App\Support\LegacyKeyValue;
use App\Support\LegacyNewsArticle;
use App\Support\LegacyNewsCategory;
use App\Support\LegacyRoom;
use App\Support\LegacyRoomData;
use App\Support\LegacyTransaction;
use App\Support\LegacyUserData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PublicSiteController extends Controller
{
    public function community(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'community');
        $articles = $this->newsArticles()->take(5)->values();

        while ($articles->count() < 5) {
            $articles->push(LegacyNewsArticle::placeholder());
        }

        return response($template->render('community', [
            'recommendedRooms' => $this->recommendedRooms(true, 5),
            'hiddenRecommendedRooms' => $this->recommendedRooms(false, 5),
            'randomHabbos' => $this->randomHabbos(18),
            'tagCloud' => $this->tagCloud(10),
            'recentTopics' => [],
            'recentHiddenTopics' => [],
            'article1' => $articles[0],
            'article2' => $articles[1],
            'article3' => $articles[2],
            'article4' => $articles[3],
            'article5' => $articles[4],
        ]));
    }

    public function articles(Request $request, LegacyTemplate $template, ?string $article = null): Response
    {
        return $this->renderNews($request, $template, 'news', null, $article);
    }

    public function events(Request $request, LegacyTemplate $template, ?string $article = null): Response
    {
        return $this->renderNews($request, $template, 'events', 'events', $article);
    }

    public function fansites(Request $request, LegacyTemplate $template, ?string $article = null): Response
    {
        return $this->renderNews($request, $template, 'fansites', 'fansites', $article);
    }

    public function games(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'games');
        $request->session()->put('gameScoreViewMonthly', true);

        return response($template->render('games', $this->highscoreContext(1, 1, true)));
    }

    public function gamesAllTime(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'games');
        $request->session()->put('gameScoreViewMonthly', false);

        return response($template->render('games', $this->highscoreContext(1, 1, false)));
    }

    public function personalHighscores(Request $request, LegacyTemplate $template): Response
    {
        $pageNumber = max(1, (int) $request->input('pageNumber', 1));
        $gameId = (int) $request->input('gameId', 1);
        $viewMonthly = (bool) $request->session()->get('gameScoreViewMonthly', true);
        $request->session()->put('highscoreGameId', (string) $gameId);

        return response($template->render('habblet/personalhighscores', $this->highscoreContext($gameId, $pageNumber, $viewMonthly)));
    }

    public function credits(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'credits');

        return response($template->render('credits'));
    }

    public function maintenance(LegacyTemplate $template): Response
    {
        return response($template->render('maintenance'));
    }

    public function creditsHistory(Request $request, LegacyTemplate $template): RedirectResponse|Response
    {
        $user = $this->currentUser($request);

        if (! $user) {
            return redirect('/');
        }

        $request->session()->put('page', 'credits');
        $period = Carbon::parse((string) $request->query('period', now()->format('Y-m-d')));
        $previous = $period->copy()->subMonth();
        $future = $period->copy()->addMonth();

        return response($template->render('credits_history', [
            'transactions' => $this->transactions($user->id, $period->month, $period->year),
            'canGoNext' => ! ($period->month === now()->month && $period->year === now()->year),
            'previousYear' => $previous->year,
            'previousMonth' => $previous->format('F'),
            'previousNumericalMonth' => $previous->month,
            'futureYear' => $future->year,
            'futureMonth' => $future->format('F'),
            'futureNumericalMonth' => $future->month,
            'currentYear' => $period->year,
            'currentMonth' => $period->format('F'),
        ]));
    }

    public function pixels(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'credits');

        return response($template->render('pixels'));
    }

    public function club(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'credits');

        return response($template->render('club', [
            'playerDetails' => $this->currentUser($request),
            'clubChoiceCredits1' => 20,
            'clubChoiceDays1' => 31,
            'clubChoiceCredits2' => 50,
            'clubChoiceDays2' => 93,
            'clubChoiceCredits3' => 80,
            'clubChoiceDays3' => 186,
            'hcDays' => 0,
            'hcSinceMonths' => 0,
        ] + $this->clubGiftContext((int) $request->session()->get('lastClubGiftMonth', 1))));
    }

    public function clubTryout(Request $request, LegacyTemplate $template): Response
    {
        $request->session()->put('page', 'credits');
        $user = $this->currentUser($request);
        $context = [
            'playerDetails' => $user,
            'clubChoiceCredits1' => 20,
            'clubChoiceDays1' => 31,
            'clubChoiceCredits2' => 50,
            'clubChoiceDays2' => 93,
            'clubChoiceCredits3' => 80,
            'clubChoiceDays3' => 186,
            'hcDays' => 0,
            'hcSinceMonths' => 0,
        ];

        if ($user) {
            $context['figure'] = (string) $user->figure;
            $context['sex'] = (string) $user->sex;

            if ((int) $user->club_expiration > time()) {
                $context['hcDays'] = (int) floor(((int) $user->club_expiration - time()) / 86400);
                $context['hcSinceMonths'] = max(0, (int) floor(((int) $user->club_subscribed > 0 ? time() - (int) $user->club_subscribed : 0) / 2678400));
            }
        }

        return response($template->render('club_tryout', $context));
    }

    public function collectables(Request $request, LegacyTemplate $template, CollectablesService $collectables): Response
    {
        $request->session()->put('page', 'credits');
        $collectable = $collectables->active();

        return response($template->render('collectables', [
            'hasCollectable' => $collectable !== null,
            'collectableSprite' => $collectable?->activeItem->sprite ?? '',
            'collectableName' => $collectable?->activeItem->name ?? '',
            'collectableDescription' => $collectable?->activeItem->description ?? '',
            'expireSeconds' => $collectable ? max(0, $collectable->expiry - time()) : 0,
            'collectablesShowroom' => $collectable?->showroom ?? [],
        ]));
    }

    public function help(LegacyTemplate $template): Response
    {
        return response($template->render('faq'));
    }

    private function renderNews(Request $request, LegacyTemplate $template, string $page, ?string $categoryIndex, ?string $articleSlug): Response
    {
        $request->session()->put('page', 'community');
        $articleId = $this->articleIdFromSlug($articleSlug);
        $articles = $this->newsArticles($categoryIndex);
        $current = $articleId > 0
            ? $articles->first(fn (LegacyNewsArticle $article): bool => $article->getId() === $articleId)
            : $articles->first();

        return response($template->render('news_articles', [
            'newsPage' => $page,
            'currentArticle' => $current ?? LegacyNewsArticle::placeholder(),
            'monthlyView' => false,
            'archiveView' => false,
            'archives' => [],
            'months' => [],
            'articlesToday' => $articles->all(),
            'articlesYesterday' => [],
            'articlesThisWeek' => [],
            'articlesThisMonth' => [],
            'articlesPastYear' => [],
            'urlSuffix' => '',
        ]));
    }

    private function articleIdFromSlug(?string $slug): int
    {
        if ($slug === null || $slug === 'archive') {
            return 0;
        }

        return (int) strtok($slug, '-');
    }

    /** @return Collection<int, LegacyNewsArticle> */
    private function newsArticles(?string $categoryIndex = null): Collection
    {
        $query = DB::table('site_articles')
            ->leftJoin('users', 'site_articles.author_id', '=', 'users.id')
            ->where('site_articles.is_published', true)
            ->orderByDesc('site_articles.created_at');

        if ($categoryIndex !== null) {
            $query->join('site_articles_categories', 'site_articles_categories.article_id', '=', 'site_articles.id')
                ->join('article_categories', 'article_categories.id', '=', 'site_articles_categories.category_id')
                ->where('article_categories.category_index', $categoryIndex);
        }

        $rows = $query
            ->get([
                'site_articles.*',
                'users.username as author_name',
            ]);

        if ($rows->isEmpty()) {
            return collect([LegacyNewsArticle::placeholder()]);
        }

        $categories = DB::table('site_articles_categories')
            ->join('article_categories', 'article_categories.id', '=', 'site_articles_categories.category_id')
            ->whereIn('site_articles_categories.article_id', $rows->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->get(['site_articles_categories.article_id', 'article_categories.id', 'article_categories.label', 'article_categories.category_index'])
            ->groupBy('article_id');

        return $rows->map(function ($row) use ($categories): LegacyNewsArticle {
            $articleCategories = ($categories->get((int) $row->id) ?? collect())
                ->map(fn ($category): LegacyNewsCategory => new LegacyNewsCategory(
                    (int) $category->id,
                    (string) $category->label,
                    (string) $category->category_index,
                ))
                ->all();

            return new LegacyNewsArticle(
                (int) $row->id,
                (string) $row->title,
                (int) $row->author_id,
                (string) ($row->author_override ?: ($row->author_name ?? 'Hotel Staff')),
                (string) $row->short_story,
                (string) $row->full_story,
                Carbon::parse($row->created_at),
                (string) $row->topstory,
                (string) $row->topstory_override,
                (string) $row->article_image,
                (bool) $row->is_published,
                $articleCategories,
            );
        });
    }

    /** @return array<string, mixed> */
    private function highscoreContext(int $gameId, int $pageNumber, bool $viewMonthly): array
    {
        $column = match ($gameId) {
            2 => $viewMonthly ? 'snowstorm_score_month' : 'snowstorm_score_all_time',
            0 => $viewMonthly ? 'wobble_squabble_score_month' : 'wobble_squabble_score_all_time',
            default => $viewMonthly ? 'battleball_score_month' : 'battleball_score_all_time',
        };
        $limit = 10;
        $offset = ($pageNumber - 1) * $limit;
        $rows = DB::table('users_statistics')
            ->join('users', 'users_statistics.user_id', '=', 'users.id')
            ->where($column, '>', 0)
            ->orderByDesc($column)
            ->offset($offset)
            ->limit($limit + 1)
            ->get(['users.username', "users_statistics.$column as score"]);

        return [
            'scoreEntries' => $rows->take($limit)
                ->values()
                ->map(fn ($row, int $index): LegacyHighscoreEntry => new LegacyHighscoreEntry(
                    $offset + $index + 1,
                    (string) $row->username,
                    (int) $row->score,
                ))
                ->all(),
            'gameId' => $gameId,
            'pageNumber' => $pageNumber,
            'viewMonthlyScores' => $viewMonthly,
            'hasNextPage' => $rows->count() > $limit,
        ];
    }

    /** @return list<LegacyTransaction> */
    private function transactions(int $userId, int $month, int $year): array
    {
        return DB::table('users_transactions')
            ->where('user_id', $userId)
            ->where('is_visible', true)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row): LegacyTransaction => new LegacyTransaction(
                $row->created_at,
                (int) $row->credit_cost,
                (string) $row->description,
            ))
            ->all();
    }

    /** @return list<LegacyRoom> */
    private function recommendedRooms(bool $staffPick, int $limit): array
    {
        $ids = DB::table('cms_recommended')
            ->where('type', 'ROOM')
            ->where('is_staff_pick', $staffPick)
            ->limit($limit)
            ->pluck('recommended_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        $owners = User::query()->get()->keyBy('id');

        return DB::table('rooms')
            ->whereIn('id', $ids)
            ->get()
            ->map(function ($row) use ($owners): LegacyRoom {
                $owner = $owners->get((int) $row->owner_id);

                return new LegacyRoom(new LegacyRoomData(
                    (int) $row->id,
                    (string) $row->name,
                    (string) $row->description,
                    $owner instanceof User ? (string) $owner->username : 'Habbo',
                    (int) $row->visitors_now,
                    (int) $row->visitors_max,
                ));
            })
            ->all();
    }

    /** @return list<LegacyUserData> */
    private function randomHabbos(int $limit): array
    {
        return User::query()
            ->orderByDesc('last_online')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): LegacyUserData => new LegacyUserData($user))
            ->all();
    }

    /** @return list<LegacyKeyValue> */
    private function tagCloud(int $limit): array
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

    /**
     * @return array{pages: list<int>, currentPage: int, lastPage: int, item: LegacyClubGiftItem}
     */
    private function clubGiftContext(int $month): array
    {
        $giftOrder = $this->clubGiftOrder();
        $month = max(1, min($month, count($giftOrder)));
        $catalogPage = match (true) {
            $month >= 21 => 5,
            $month >= 17 => 4,
            $month >= 13 => 3,
            $month >= 9 => 2,
            $month >= 5 => 1,
            default => 0,
        };
        $pages = match ($catalogPage) {
            1 => [5, 6, 7, 8, 9],
            2 => [9, 10, 11, 12, 13],
            3 => [13, 14, 15, 16, 17],
            4 => [17, 18, 19, 20, 21],
            5 => [19, 20, 21, 22, 23],
            default => [1, 2, 3, 4, 5],
        };
        $sprite = $giftOrder[$month - 1];
        $name = DB::table('items_definitions')->where('sprite', $sprite)->value('name');

        return [
            'pages' => $pages,
            'currentPage' => $month,
            'lastPage' => count($giftOrder),
            'item' => new LegacyClubGiftItem($sprite, $name ? (string) $name : str_replace('_', ' ', $sprite)),
        ];
    }

    /** @return list<string> */
    private function clubGiftOrder(): array
    {
        return [
            'club_sofa',
            'hc_tv',
            'hcamme',
            'hc_crtn',
            'mocchamaster',
            'hc_crpt',
            'edicehc',
            'hc_wall_lamp',
            'doorD',
            'deal_hcrollers',
            'hcsohva',
            'hc_bkshlf',
            'hc_lmp',
            'hc_trll',
            'hc_tbl',
            'hc_machine',
            'hc_chr',
            'hc_rntgn',
            'hc_dsk',
            'hc_djset',
            'hc_lmpst',
            'hc_frplc',
            'hc_btlr',
        ];
    }
}
