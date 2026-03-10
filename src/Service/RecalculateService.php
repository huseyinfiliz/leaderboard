<?php

namespace HuseyinFiliz\Leaderboard\Service;

use Flarum\Extension\ExtensionManager;
use Illuminate\Database\ConnectionInterface;

class RecalculateService
{
    protected string $prefix;

    private const SYNC_STEPS = [
        'discussion_started',
        'post_created',
        'like_received',
        'like_given',
        'reaction_received',
        'reaction_given',
        'best_answer',
        'vote_received',
        'badge_earned',
        'rebuild_totals',
    ];

    public function __construct(
        protected PointService $pointService,
        protected ConnectionInterface $db,
        protected ExtensionManager $extensions
    ) {
        $this->prefix = $db->getTablePrefix();
    }

    /**
     * Execute a single sync step. Each step syncs one reason then
     * the final step rebuilds totals.
     *
     * @return array{step: int, totalSteps: int, stepKey: string, done: bool}
     */
    public function executeSyncStep(int $step): array
    {
        $steps = self::SYNC_STEPS;
        $totalSteps = count($steps);

        if ($step >= $totalSteps) {
            return ['step' => $step, 'totalSteps' => $totalSteps, 'stepKey' => 'done', 'done' => true];
        }

        $currentStep = $steps[$step];

        $excludedGroupIds = $this->pointService->getExcludedGroupIds();
        $excludedTagIds = $this->pointService->getExcludedTagIds();

        switch ($currentStep) {
            case 'discussion_started':
                $this->syncDiscussionStarted($excludedGroupIds, $excludedTagIds);
                break;
            case 'post_created':
                $this->syncPostCreated($excludedGroupIds, $excludedTagIds);
                break;
            case 'like_received':
                if ($this->extensions->isEnabled('flarum-likes')) {
                    $this->syncLikeReceived($excludedGroupIds, $excludedTagIds);
                } else {
                    $this->purgeReason('like_received');
                }
                break;
            case 'like_given':
                if ($this->extensions->isEnabled('flarum-likes')) {
                    $this->syncLikeGiven($excludedGroupIds, $excludedTagIds);
                } else {
                    $this->purgeReason('like_given');
                }
                break;
            case 'reaction_received':
                if ($this->extensions->isEnabled('fof-reactions')) {
                    $this->syncReactionReceived($excludedGroupIds, $excludedTagIds);
                } else {
                    $this->purgeReason('reaction_received');
                }
                break;
            case 'reaction_given':
                if ($this->extensions->isEnabled('fof-reactions')) {
                    $this->syncReactionGiven($excludedGroupIds, $excludedTagIds);
                } else {
                    $this->purgeReason('reaction_given');
                }
                break;
            case 'best_answer':
                if ($this->extensions->isEnabled('fof-best-answer')) {
                    $this->syncBestAnswer($excludedGroupIds, $excludedTagIds);
                } else {
                    $this->purgeReason('best_answer');
                }
                break;
            case 'vote_received':
                if ($this->extensions->isEnabled('fof-gamification')) {
                    $this->syncVoteReceived($excludedGroupIds, $excludedTagIds);
                } else {
                    $this->purgeReason('upvote_received');
                    $this->purgeReason('downvote_received');
                }
                break;
            case 'badge_earned':
                if ($this->extensions->isEnabled('fof-badges')) {
                    $this->syncBadgeEarned($excludedGroupIds);
                } else {
                    $this->purgeReason('badge_earned');
                }
                break;
            case 'rebuild_totals':
                $this->rebuildTotals();
                break;
        }

        $done = $step >= $totalSteps - 1;

        return [
            'step' => $step,
            'totalSteps' => $totalSteps,
            'stepKey' => $currentStep,
            'done' => $done,
        ];
    }

    /**
     * Rebuild all user totals using COUNT × current point settings.
     * Can be called standalone (e.g. after admin changes point values).
     */
    public function rebuildTotals(): void
    {
        $pointsCase = $this->pointService->buildPointsCaseSql();
        $p = $this->prefix;

        $this->db->table('leaderboard_user_totals')->delete();

        $sql = "INSERT INTO {$p}leaderboard_user_totals (user_id, points_total)
                SELECT user_id, SUM({$pointsCase['sql']})
                FROM {$p}leaderboard_points
                GROUP BY user_id";

        $this->db->insert($sql, $pointsCase['bindings']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Sync methods: delete orphans + insert missing
    // ──────────────────────────────────────────────────────────────

    private function syncDiscussionStarted(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'discussion_started'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}discussions d
                   JOIN {$p}users u ON u.id = d.user_id
                   WHERE d.id = lp.subject_id
                     AND d.user_id = lp.user_id
                     AND d.hidden_at IS NULL "
            . $this->groupExclusionSql($excludedGroupIds, 'd.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'd.id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, created_at)
                SELECT d.user_id, 'discussion_started', d.id, 'discussion', d.created_at
                FROM {$p}discussions d
                JOIN {$p}users u ON u.id = d.user_id
                WHERE d.hidden_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'discussion_started'
                        AND lp.subject_id = d.id
                        AND lp.user_id = d.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'd.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'd.id');

        $this->db->insert($sql);
    }

    private function syncPostCreated(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'post_created'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}posts p
                   JOIN {$p}users u ON u.id = p.user_id
                   WHERE p.id = lp.subject_id
                     AND p.user_id = lp.user_id
                     AND p.type = 'comment' AND p.number > 1
                     AND p.hidden_at IS NULL "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, created_at)
                SELECT p.user_id, 'post_created', p.id, 'post', p.created_at
                FROM {$p}posts p
                JOIN {$p}users u ON u.id = p.user_id
                WHERE p.type = 'comment' AND p.number > 1
                  AND p.hidden_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'post_created'
                        AND lp.subject_id = p.id
                        AND lp.user_id = p.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id');

        $this->db->insert($sql);
    }

    private function syncLikeReceived(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'like_received'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}post_likes pl
                   JOIN {$p}posts p ON p.id = pl.post_id
                   JOIN {$p}users u ON u.id = p.user_id
                   WHERE pl.post_id = lp.subject_id
                     AND pl.user_id = lp.actor_id
                     AND p.user_id != pl.user_id "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, actor_id, created_at)
                SELECT p.user_id, 'like_received', pl.post_id, 'post', pl.user_id,
                       COALESCE(pl.created_at, NOW())
                FROM {$p}post_likes pl
                JOIN {$p}posts p ON p.id = pl.post_id
                JOIN {$p}users u ON u.id = p.user_id
                WHERE p.user_id != pl.user_id
                  AND NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'like_received'
                        AND lp.subject_id = pl.post_id
                        AND lp.actor_id = pl.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id');

        $this->db->insert($sql);
    }

    private function syncLikeGiven(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'like_given'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}post_likes pl
                   JOIN {$p}posts p ON p.id = pl.post_id
                   JOIN {$p}users u ON u.id = pl.user_id
                   WHERE pl.post_id = lp.subject_id
                     AND pl.user_id = lp.user_id "
            . $this->groupExclusionSql($excludedGroupIds, 'pl.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, actor_id, created_at)
                SELECT pl.user_id, 'like_given', pl.post_id, 'post', pl.user_id,
                       COALESCE(pl.created_at, NOW())
                FROM {$p}post_likes pl
                JOIN {$p}posts p ON p.id = pl.post_id
                JOIN {$p}users u ON u.id = pl.user_id
                WHERE NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'like_given'
                        AND lp.subject_id = pl.post_id
                        AND lp.user_id = pl.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'pl.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id');

        $this->db->insert($sql);
    }

    private function syncReactionReceived(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'reaction_received'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}post_reactions pr
                   JOIN {$p}posts p ON p.id = pr.post_id
                   JOIN {$p}users u ON u.id = p.user_id
                   WHERE pr.post_id = lp.subject_id
                     AND pr.user_id = lp.actor_id
                     AND p.user_id != pr.user_id "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, actor_id, created_at)
                SELECT p.user_id, 'reaction_received', pr.post_id, 'post', pr.user_id,
                       COALESCE(pr.created_at, NOW())
                FROM {$p}post_reactions pr
                JOIN {$p}posts p ON p.id = pr.post_id
                JOIN {$p}users u ON u.id = p.user_id
                WHERE p.user_id != pr.user_id
                  AND NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'reaction_received'
                        AND lp.subject_id = pr.post_id
                        AND lp.actor_id = pr.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id');

        $this->db->insert($sql);
    }

    private function syncReactionGiven(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'reaction_given'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}post_reactions pr
                   JOIN {$p}posts p ON p.id = pr.post_id
                   JOIN {$p}users u ON u.id = pr.user_id
                   WHERE pr.post_id = lp.subject_id
                     AND pr.user_id = lp.user_id "
            . $this->groupExclusionSql($excludedGroupIds, 'pr.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, actor_id, created_at)
                SELECT pr.user_id, 'reaction_given', pr.post_id, 'post', pr.user_id,
                       COALESCE(pr.created_at, NOW())
                FROM {$p}post_reactions pr
                JOIN {$p}posts p ON p.id = pr.post_id
                JOIN {$p}users u ON u.id = pr.user_id
                WHERE NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'reaction_given'
                        AND lp.subject_id = pr.post_id
                        AND lp.user_id = pr.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'pr.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id');

        $this->db->insert($sql);
    }

    private function syncBestAnswer(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'best_answer'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}discussions d
                   JOIN {$p}posts p ON p.id = d.best_answer_post_id
                   JOIN {$p}users u ON u.id = p.user_id
                   WHERE d.id = lp.subject_id
                     AND p.user_id = lp.user_id
                     AND d.best_answer_post_id IS NOT NULL "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'd.id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, created_at)
                SELECT p.user_id, 'best_answer', d.id, 'discussion',
                       COALESCE(d.best_answer_set_at, d.created_at)
                FROM {$p}discussions d
                JOIN {$p}posts p ON p.id = d.best_answer_post_id
                JOIN {$p}users u ON u.id = p.user_id
                WHERE d.best_answer_post_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = 'best_answer'
                        AND lp.subject_id = d.id
                        AND lp.user_id = p.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'd.id');

        $this->db->insert($sql);
    }

    private function syncVoteReceived(array $excludedGroupIds, array $excludedTagIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason IN ('upvote_received', 'downvote_received')
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}post_votes pv
                   JOIN {$p}posts p ON p.id = pv.post_id
                   JOIN {$p}users u ON u.id = p.user_id
                   WHERE pv.post_id = lp.subject_id
                     AND pv.user_id = lp.actor_id
                     AND pv.value IN (1, -1)
                     AND p.user_id != pv.user_id "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, actor_id, created_at)
                SELECT p.user_id,
                       CASE WHEN pv.value = 1 THEN 'upvote_received' ELSE 'downvote_received' END,
                       pv.post_id, 'post', pv.user_id,
                       COALESCE(pv.created_at, NOW())
                FROM {$p}post_votes pv
                JOIN {$p}posts p ON p.id = pv.post_id
                JOIN {$p}users u ON u.id = p.user_id
                WHERE pv.value IN (1, -1)
                  AND p.user_id != pv.user_id
                  AND NOT EXISTS (
                      SELECT 1 FROM {$p}leaderboard_points lp
                      WHERE lp.reason = CASE WHEN pv.value = 1 THEN 'upvote_received' ELSE 'downvote_received' END
                        AND lp.subject_id = pv.post_id
                        AND lp.actor_id = pv.user_id
                  ) "
            . $this->groupExclusionSql($excludedGroupIds, 'p.user_id')
            . $this->tagExclusionSql($excludedTagIds, 'p.discussion_id');

        $this->db->insert($sql);
    }

    private function syncBadgeEarned(array $excludedGroupIds): void
    {
        $p = $this->prefix;

        $this->db->delete(
            "DELETE lp FROM {$p}leaderboard_points lp
             WHERE lp.reason = 'badge_earned'
               AND NOT EXISTS (
                   SELECT 1 FROM {$p}fof_badge_user ub
                   JOIN {$p}users u ON u.id = ub.user_id
                   WHERE ub.badge_id = lp.subject_id
                     AND ub.user_id = lp.user_id "
            . $this->groupExclusionSql($excludedGroupIds, 'ub.user_id')
            . ')'
        );

        $sql = "INSERT INTO {$p}leaderboard_points (user_id, reason, subject_id, subject_type, created_at)
                SELECT ub.user_id, 'badge_earned', ub.badge_id, 'badge', ub.earned_at
                FROM {$p}fof_badge_user ub
                JOIN {$p}users u ON u.id = ub.user_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$p}leaderboard_points lp
                    WHERE lp.reason = 'badge_earned'
                      AND lp.subject_id = ub.badge_id
                      AND lp.user_id = ub.user_id
                ) "
            . $this->groupExclusionSql($excludedGroupIds, 'ub.user_id');

        $this->db->insert($sql);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function purgeReason(string $reason): void
    {
        $this->db->table('leaderboard_points')->where('reason', $reason)->delete();
    }

    private function groupExclusionSql(array $ids, string $col): string
    {
        if (empty($ids)) {
            return '';
        }

        $list = implode(',', array_map('intval', $ids));

        return "AND $col NOT IN (SELECT user_id FROM {$this->prefix}group_user WHERE group_id IN ($list)) ";
    }

    private function tagExclusionSql(array $ids, string $discussionIdCol): string
    {
        if (empty($ids) || !$this->extensions->isEnabled('flarum-tags')) {
            return '';
        }

        $list = implode(',', array_map('intval', $ids));

        return "AND $discussionIdCol NOT IN (SELECT discussion_id FROM {$this->prefix}discussion_tag WHERE tag_id IN ($list)) ";
    }
}
