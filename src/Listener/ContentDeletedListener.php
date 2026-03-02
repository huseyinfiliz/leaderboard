<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Post\Post;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Model\LeaderboardPoint;
use HuseyinFiliz\Leaderboard\Model\LeaderboardUserTotal;
use HuseyinFiliz\Leaderboard\Service\PointService;
use Illuminate\Contracts\Events\Dispatcher;

class ContentDeletedListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostDeleted::class, [$this, 'handlePostDeleted']);
        $events->listen(DiscussionDeleted::class, [$this, 'handleDiscussionDeleted']);
    }

    public function handlePostDeleted(PostDeleted $event): void
    {
        $user = $event->post->user;

        if (!$user) {
            return;
        }

        $this->pointService->revoke(
            $user,
            'post_created',
            $event->post->id,
            'post'
        );

        // Also revoke any like/reaction/vote points tied to this post
        $this->revokeRelatedPoints($event->post->id);
    }

    public function handleDiscussionDeleted(DiscussionDeleted $event): void
    {
        $user = $event->discussion->user;

        if ($user) {
            $this->pointService->revoke(
                $user,
                'discussion_started',
                $event->discussion->id,
                'discussion'
            );
        }

        // Revoke best_answer points for this discussion (could belong to any user)
        $this->pointService->revokeBulkBySubject('best_answer', $event->discussion->id, 'discussion');

        // Flarum cascades post deletion via SQL (no PostDeleted events fired),
        // so we must bulk-revoke all post-level points for this discussion's posts.
        $this->revokeDiscussionPostPoints($event->discussion->id);
    }

    /**
     * Revoke engagement points for a single post (likes, reactions, votes).
     * These use revokeBulkBySubject because the points may belong to different users.
     */
    protected function revokeRelatedPoints(int $postId): void
    {
        $reasons = ['post_created', 'like_received', 'like_given', 'reaction_received', 'reaction_given', 'upvote_received', 'downvote_received'];

        foreach ($reasons as $reason) {
            $this->pointService->revokeBulkBySubject($reason, $postId, 'post');
        }
    }

    /**
     * When a discussion is deleted, Flarum cascades post deletion via SQL
     * without firing individual PostDeleted events. We must manually find
     * all post IDs that had leaderboard points and bulk-revoke them.
     */
    protected function revokeDiscussionPostPoints(int $discussionId): void
    {
        // Get post IDs that belong to this discussion and have leaderboard points.
        // Posts may already be cascade-deleted from DB, so we query leaderboard_points directly.
        $postReasons = ['post_created', 'like_received', 'reaction_received', 'upvote_received', 'downvote_received'];

        // First, try to get post IDs from the posts table (before cascade runs)
        $postIds = Post::where('discussion_id', $discussionId)->pluck('id')->all();

        if (empty($postIds)) {
            return;
        }

        // Chunk to avoid memory issues on large discussions
        foreach (array_chunk($postIds, 500) as $chunk) {
            // Calculate per-user point decrements for this chunk
            $rows = LeaderboardPoint::whereIn('subject_id', $chunk)
                ->where('subject_type', 'post')
                ->whereIn('reason', $postReasons)
                ->selectRaw('user_id, reason, COUNT(*) as cnt')
                ->groupBy('user_id', 'reason')
                ->get();

            // Aggregate total points to deduct per user
            $decrements = [];

            foreach ($rows as $row) {
                $pts = $row->cnt * $this->pointService->getPointsForReason($row->reason);

                if ($pts != 0) {
                    $decrements[$row->user_id] = ($decrements[$row->user_id] ?? 0) + $pts;
                }
            }

            // Delete point records for this chunk
            LeaderboardPoint::whereIn('subject_id', $chunk)
                ->where('subject_type', 'post')
                ->whereIn('reason', $postReasons)
                ->delete();

            // Apply decrements
            foreach ($decrements as $userId => $totalPts) {
                if ($totalPts != 0) {
                    LeaderboardUserTotal::where('user_id', $userId)
                        ->decrement('points_total', $totalPts);
                }
            }
        }
    }
}
