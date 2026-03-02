<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Post\Event\Restored as PostRestored;
use HuseyinFiliz\Leaderboard\Model\LeaderboardPoint;
use HuseyinFiliz\Leaderboard\Service\PointService;
use Illuminate\Contracts\Events\Dispatcher;

class ContentRestoredListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostRestored::class, [$this, 'handlePostRestored']);
        $events->listen(DiscussionRestored::class, [$this, 'handleDiscussionRestored']);
    }

    public function handlePostRestored(PostRestored $event): void
    {
        $user = $event->post->user;

        if (!$user) {
            return;
        }

        // Skip first post — discussion_started covers it
        if ((int) $event->post->number <= 1) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($user)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->post->discussion)) {
            return;
        }

        // Idempotency: only award if not already present
        $exists = LeaderboardPoint::where('user_id', $user->id)
            ->where('reason', 'post_created')
            ->where('subject_id', $event->post->id)
            ->where('subject_type', 'post')
            ->exists();

        if ($exists) {
            return;
        }

        $points = $this->pointService->getPointsForReason('post_created');

        $this->pointService->award(
            $user,
            $points,
            'post_created',
            $event->post->id,
            'post'
        );
    }

    public function handleDiscussionRestored(DiscussionRestored $event): void
    {
        $user = $event->discussion->user;

        if (!$user) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($user)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->discussion)) {
            return;
        }

        // Idempotency: only award if not already present
        $exists = LeaderboardPoint::where('user_id', $user->id)
            ->where('reason', 'discussion_started')
            ->where('subject_id', $event->discussion->id)
            ->where('subject_type', 'discussion')
            ->exists();

        if ($exists) {
            return;
        }

        $points = $this->pointService->getPointsForReason('discussion_started');

        $this->pointService->award(
            $user,
            $points,
            'discussion_started',
            $event->discussion->id,
            'discussion'
        );
    }
}
