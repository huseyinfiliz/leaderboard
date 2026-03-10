<?php

namespace HuseyinFiliz\Leaderboard\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Api\Data\LeaderboardEntryData;
use HuseyinFiliz\Leaderboard\Model\LeaderboardPoint;
use HuseyinFiliz\Leaderboard\Model\LeaderboardUserTotal;
use HuseyinFiliz\Leaderboard\Service\PointService;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ListLeaderboardController implements RequestHandlerInterface
{
    protected int $limit = 20;

    protected int $maxLimit = 50;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $url,
        protected PointService $pointService
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $filter = Arr::get($params, 'filter', []);
        $period = is_array($filter) ? Arr::get($filter, 'period', 'all') : 'all';
        $section = is_array($filter) ? Arr::get($filter, 'section', '') : '';

        $excludedGroupIds = $this->getExcludedGroupIds();

        switch ($section) {
            case 'podium':
                $offset = 0;
                $limit = 3;
                break;

            case 'contenders':
                $offset = 3;
                $limit = 7;
                break;

            case 'honorable':
                $sectionOffset = $this->extractOffset($params);
                $offset = 10 + $sectionOffset;
                $limit = $this->extractLimit($params);
                break;

            default:
                $offset = $this->extractOffset($params);
                $limit = $this->extractLimit($params);
                break;
        }

        if ($period === 'all') {
            $results = $this->getAllTimeResults($offset, $limit, $excludedGroupIds);
        } else {
            $periodStart = $this->getPeriodStart($period);
            $results = $this->getPeriodResults($periodStart, $offset, $limit, $excludedGroupIds);
        }

        $entries = $results['entries'];
        $total = $results['total'];

        // Build JSON:API response
        $data = [];
        $included = [];
        $seenUsers = [];

        foreach ($entries as $entry) {
            $entryData = [
                'type' => 'leaderboard-entries',
                'id' => (string) $entry->id,
                'attributes' => [
                    'points' => $entry->points,
                    'rank' => $entry->rank,
                ],
            ];

            if ($entry->user) {
                $entryData['relationships'] = [
                    'user' => [
                        'data' => ['type' => 'users', 'id' => (string) $entry->user->id],
                    ],
                ];

                if (!isset($seenUsers[$entry->user->id])) {
                    $included[] = $this->serializeUser($entry->user);
                    $seenUsers[$entry->user->id] = true;
                }
            }

            $data[] = $entryData;
        }

        $response = ['data' => $data];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        // Pagination links
        if ($section === 'honorable') {
            $sectionOffset = $this->extractOffset($params);
            $hasMore = $total > $offset + count($entries);
            $response['links'] = $this->buildPaginationLinks($request, $sectionOffset, $limit, $hasMore);
        } elseif ($section === '') {
            $hasMore = $total > $offset + count($entries);
            $response['links'] = $this->buildPaginationLinks($request, $offset, $limit, $hasMore);
        }

        return new JsonResponse($response);
    }

    protected function serializeUser(User $user): array
    {
        $attributes = [
            'username' => $user->username,
            'displayName' => $user->display_name,
            'slug' => $user->username,
        ];

        if ($user->avatar_url) {
            $attributes['avatarUrl'] = $user->avatar_url;
        }

        if ($user->comment_count !== null) {
            $attributes['commentCount'] = (int) $user->comment_count;
        }

        if ($user->discussion_count !== null) {
            $attributes['discussionCount'] = (int) $user->discussion_count;
        }

        return [
            'type' => 'users',
            'id' => (string) $user->id,
            'attributes' => $attributes,
        ];
    }

    protected function buildPaginationLinks(ServerRequestInterface $request, int $offset, int $limit, bool $hasMore): array
    {
        $links = [];
        $baseUrl = $this->url->to('api')->route('huseyinfiliz-leaderboard.api.index');
        $queryParams = $request->getQueryParams();

        if ($offset > 0) {
            $firstParams = $queryParams;
            $firstParams['page'] = ['offset' => 0];
            $links['first'] = $baseUrl . '?' . http_build_query($firstParams, '', '&', PHP_QUERY_RFC3986);

            $prevOffset = max(0, $offset - $limit);
            $prevParams = $queryParams;
            $prevParams['page'] = ['offset' => $prevOffset];
            $links['prev'] = $baseUrl . '?' . http_build_query($prevParams, '', '&', PHP_QUERY_RFC3986);
        }

        if ($hasMore) {
            $nextParams = $queryParams;
            $nextParams['page'] = ['offset' => $offset + $limit];
            $links['next'] = $baseUrl . '?' . http_build_query($nextParams, '', '&', PHP_QUERY_RFC3986);
        }

        return $links;
    }

    protected function extractOffset(array $params): int
    {
        $page = Arr::get($params, 'page', []);

        return max(0, (int) Arr::get($page, 'offset', 0));
    }

    protected function extractLimit(array $params): int
    {
        $page = Arr::get($params, 'page', []);
        $limit = (int) Arr::get($page, 'limit', $this->limit);

        return max(1, min($limit, $this->maxLimit));
    }

    protected function getAllTimeResults(int $offset, int $limit, array $excludedGroupIds): array
    {
        $query = LeaderboardUserTotal::query()
            ->where('points_total', '>', 0)
            ->orderBy('points_total', 'desc')
            ->orderBy('user_id');

        if (!empty($excludedGroupIds)) {
            $query->whereNotIn('user_id', function ($sub) use ($excludedGroupIds) {
                $sub->select('user_id')
                    ->from('group_user')
                    ->whereIn('group_id', $excludedGroupIds);
            });
        }

        $total = $query->count();
        $rows = $query->offset($offset)->limit($limit)->get();

        $userIds = $rows->pluck('user_id')->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $entries = [];
        foreach ($rows as $index => $row) {
            $user = $users->get($row->user_id);
            if (!$user) {
                continue;
            }

            $entries[] = new LeaderboardEntryData(
                $user->id,
                $row->points_total,
                $offset + $index + 1,
                $user
            );
        }

        return ['entries' => $entries, 'total' => $total];
    }

    protected function getPeriodResults(Carbon $periodStart, int $offset, int $limit, array $excludedGroupIds): array
    {
        $pointsCase = $this->pointService->buildPointsCaseSql();

        $query = LeaderboardPoint::query()
            ->selectRaw("user_id, SUM({$pointsCase['sql']}) as period_points", $pointsCase['bindings'])
            ->where('created_at', '>=', $periodStart)
            ->groupBy('user_id')
            ->havingRaw('period_points > 0')
            ->orderByDesc('period_points')
            ->orderBy('user_id');

        if (!empty($excludedGroupIds)) {
            $query->whereNotIn('user_id', function ($sub) use ($excludedGroupIds) {
                $sub->select('user_id')
                    ->from('group_user')
                    ->whereIn('group_id', $excludedGroupIds);
            });
        }

        $total = (clone $query)->getQuery()->getCountForPagination();

        $rows = $query->offset($offset)->limit($limit)->get();

        $userIds = $rows->pluck('user_id')->all();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $entries = [];
        foreach ($rows as $index => $row) {
            $user = $users->get($row->user_id);
            if (!$user) {
                continue;
            }

            $entries[] = new LeaderboardEntryData(
                $user->id,
                (int) $row->period_points,
                $offset + $index + 1,
                $user
            );
        }

        return ['entries' => $entries, 'total' => $total];
    }

    protected function getPeriodStart(string $period): Carbon
    {
        $now = Carbon::now();

        switch ($period) {
            case 'daily':
                return $now->copy()->startOfDay();
            case 'weekly':
                return $now->copy()->startOfWeek(Carbon::MONDAY);
            case 'monthly':
                return $now->copy()->startOfMonth();
            case 'quarterly':
                return $now->copy()->firstOfQuarter();
            case 'yearly':
                return $now->copy()->startOfYear();
            default:
                return $now->copy()->startOfDay();
        }
    }

    protected function getExcludedGroupIds(): array
    {
        $value = $this->settings->get('huseyinfiliz-leaderboard.excluded_groups', '');

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
