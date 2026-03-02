<?php

namespace HuseyinFiliz\Leaderboard\Api\Controller;

use Carbon\Carbon;
use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Api\Data\LeaderboardEntryData;
use HuseyinFiliz\Leaderboard\Api\Serializer\LeaderboardEntryLeanSerializer;
use HuseyinFiliz\Leaderboard\Api\Serializer\LeaderboardEntrySerializer;
use HuseyinFiliz\Leaderboard\Model\LeaderboardPoint;
use HuseyinFiliz\Leaderboard\Model\LeaderboardUserTotal;
use HuseyinFiliz\Leaderboard\Service\PointService;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListLeaderboardController extends AbstractListController
{
    public $serializer = LeaderboardEntrySerializer::class;

    public $include = ['user'];

    public $limit = 20;

    public $maxLimit = 50;

    protected SettingsRepositoryInterface $settings;
    protected UrlGenerator $url;
    protected PointService $pointService;

    public function __construct(SettingsRepositoryInterface $settings, UrlGenerator $url, PointService $pointService)
    {
        $this->settings = $settings;
        $this->url = $url;
        $this->pointService = $pointService;
    }

    protected function data(ServerRequestInterface $request, Document $document)
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
                $this->serializer = LeaderboardEntryLeanSerializer::class;
                break;

            case 'honorable':
                $sectionOffset = $this->extractOffset($request);
                $offset = 10 + $sectionOffset;
                $limit = $this->extractLimit($request);
                $this->serializer = LeaderboardEntryLeanSerializer::class;
                break;

            default:
                // Legacy: no section filter — return all entries like before
                $offset = $this->extractOffset($request);
                $limit = $this->extractLimit($request);
                break;
        }

        if ($period === 'all') {
            $results = $this->getAllTimeResults($offset, $limit, $excludedGroupIds);
        } else {
            $periodStart = $this->getPeriodStart($period);
            $results = $this->getPeriodResults($periodStart, $offset, $limit, $excludedGroupIds);
        }

        if ($section === 'honorable') {
            // Pagination relative to the honorable section (offset 0, 20, 40...)
            $sectionOffset = $this->extractOffset($request);
            $hasMore = $results['total'] > $offset + count($results['entries']);

            $document->addPaginationLinks(
                $this->url->to('api')->route('huseyinfiliz-leaderboard.api.index'),
                $request->getQueryParams(),
                $sectionOffset,
                $limit,
                $hasMore ? null : 0
            );
        } elseif ($section === '') {
            // Legacy pagination
            $hasMore = $results['total'] > $offset + count($results['entries']);

            $document->addPaginationLinks(
                $this->url->to('api')->route('huseyinfiliz-leaderboard.api.index'),
                $request->getQueryParams(),
                $offset,
                $limit,
                $hasMore ? null : 0
            );
        }
        // podium and contenders: no pagination needed

        return $results['entries'];
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
