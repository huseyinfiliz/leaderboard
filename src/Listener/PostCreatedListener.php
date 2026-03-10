<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Post\Event\Posted;
use HuseyinFiliz\Leaderboard\Service\PointService;

class PostCreatedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(Posted $event): void
    {
        if (!$event->actor) {
            return;
        }

        // Skip if this is the first post (discussion creation already awards points)
        if ((int) $event->post->number <= 1) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($event->actor)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->post->discussion)) {
            return;
        }

        $points = $this->pointService->getPointsForReason('post_created');

        $this->pointService->award(
            $event->actor,
            $points,
            'post_created',
            $event->post->id,
            'post'
        );
    }
}
