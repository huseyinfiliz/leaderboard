<?php

namespace HuseyinFiliz\Leaderboard\Service;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Model\LeaderboardPoint;
use HuseyinFiliz\Leaderboard\Model\LeaderboardUserTotal;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

class PointService
{
    protected array $defaults = [
        'discussion_started' => 1,
        'post_created' => 1,
        'daily_login' => 1,
        'like_received' => 1,
        'like_given' => 0,
        'reaction_received' => 1,
        'reaction_given' => 0,
        'best_answer' => 2,
        'badge_earned' => 3,
        'upvote_received' => 1,
        'downvote_received' => -1,
    ];

    public function __construct(protected SettingsRepositoryInterface $settings, protected Cache $cache, protected ConnectionInterface $db, protected ExtensionManager $extensions)
    {
    }

    public function award(User $user, int $points, string $reason, ?int $subjectId = null, ?string $subjectType = null, ?int $actorId = null): void
    {
        if ($points === 0) {
            return;
        }

        $this->ensureUserTotal($user);

        LeaderboardPoint::create([
            'user_id' => $user->id,
            'reason' => $reason,
            'subject_id' => $subjectId,
            'subject_type' => $subjectType,
            'actor_id' => $actorId,
            'created_at' => Carbon::now(),
        ]);

        LeaderboardUserTotal::where('user_id', $user->id)
            ->increment('points_total', $points);

        if ($reason !== 'daily_login') {
            $this->checkDailyLogin($user);
        }
    }

    public function revoke(User $user, string $reason, ?int $subjectId = null, ?string $subjectType = null, ?int $actorId = null): void
    {
        $this->db->transaction(function () use ($user, $reason, $subjectId, $subjectType, $actorId) {
            $query = LeaderboardPoint::where('user_id', $user->id)
                ->where('reason', $reason);

            if ($subjectId !== null) {
                $query->where('subject_id', $subjectId);
            }

            if ($subjectType !== null) {
                $query->where('subject_type', $subjectType);
            }

            if ($actorId !== null) {
                $query->where('actor_id', $actorId);
            }

            $count = $query->lockForUpdate()->count();

            if ($count === 0) {
                return;
            }

            $query->delete();

            $totalPoints = $count * $this->getPointsForReason($reason);

            if ($totalPoints !== 0) {
                LeaderboardUserTotal::where('user_id', $user->id)
                    ->decrement('points_total', $totalPoints);
            }
        });
    }

    public function revokeBulkBySubject(string $reason, int $subjectId, ?string $subjectType = null, ?int $actorId = null): void
    {
        $query = LeaderboardPoint::where('reason', $reason)
            ->where('subject_id', $subjectId);

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }

        if ($actorId !== null) {
            $query->where('actor_id', $actorId);
        }

        $pointsPerAction = $this->getPointsForReason($reason);

        if ($pointsPerAction != 0) {
            $countsByUser = (clone $query)->selectRaw('user_id, COUNT(*) as cnt')
                ->groupBy('user_id')
                ->pluck('cnt', 'user_id');

            foreach ($countsByUser as $userId => $count) {
                LeaderboardUserTotal::where('user_id', $userId)
                    ->decrement('points_total', $count * $pointsPerAction);
            }
        }

        $query->delete();
    }

    public function ensureUserTotal(User $user): void
    {
        LeaderboardUserTotal::firstOrCreate(
            ['user_id' => $user->id],
            ['points_total' => 0]
        );
    }

    public function getPointsForReason(string $reason): int
    {
        $default = $this->defaults[$reason] ?? 0;

        return (int) $this->settings->get("huseyinfiliz-leaderboard.points_{$reason}", $default);
    }

    public function isExcludedByTags(Discussion $discussion): bool
    {
        if (!$this->extensions->isEnabled('flarum-tags')) {
            return false;
        }

        $excludedTagIds = $this->getExcludedTagIds();

        if (empty($excludedTagIds)) {
            return false;
        }

        return $this->db->table('discussion_tag')
            ->where('discussion_id', $discussion->id)
            ->whereIn('tag_id', $excludedTagIds)
            ->exists();
    }

    public function isExcludedByGroup(User $user): bool
    {
        $excludedGroupIds = $this->getExcludedGroupIds();

        if (empty($excludedGroupIds)) {
            return false;
        }

        return $user->groups()->whereIn('groups.id', $excludedGroupIds)->exists();
    }

    public function checkDailyLogin(User $user): void
    {
        $today = Carbon::today()->toDateString();
        $cacheKey = "leaderboard_daily_login:{$user->id}:{$today}";

        if ($this->cache->has($cacheKey)) {
            return;
        }

        // DB fallback: if cache was cleared, check if already awarded today
        $alreadyAwarded = LeaderboardPoint::where('user_id', $user->id)
            ->where('reason', 'daily_login')
            ->whereDate('created_at', $today)
            ->exists();

        if ($alreadyAwarded) {
            $this->cache->put($cacheKey, true, 86400);

            return;
        }

        $points = $this->getPointsForReason('daily_login');

        if ($points !== 0) {
            $this->award($user, $points, 'daily_login');
        }

        $this->cache->put($cacheKey, true, 86400);
    }

    public function getExcludedTagIds(): array
    {
        $value = $this->settings->get('huseyinfiliz-leaderboard.excluded_tags', '');

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a CASE expression that maps each reason to its current point value.
     *
     * Returns ['sql' => 'CASE reason WHEN ? THEN ? ... END', 'bindings' => [...]]
     */
    public function buildPointsCaseSql(): array
    {
        $reasons = array_keys($this->defaults);

        $case = 'CASE reason';
        $bindings = [];

        foreach ($reasons as $reason) {
            $pts = $this->getPointsForReason($reason);
            $case .= ' WHEN ? THEN ?';
            $bindings[] = $reason;
            $bindings[] = $pts;
        }

        $case .= ' ELSE 0 END';

        return ['sql' => $case, 'bindings' => $bindings];
    }

    public function getExcludedGroupIds(): array
    {
        $value = $this->settings->get('huseyinfiliz-leaderboard.excluded_groups', '');

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
