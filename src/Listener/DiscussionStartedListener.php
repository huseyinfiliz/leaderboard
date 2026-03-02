<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Discussion\Event\Started;
use HuseyinFiliz\Leaderboard\Service\PointService;

class DiscussionStartedListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(Started $event): void
    {
        if (!$event->actor) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($event->actor)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->discussion)) {
            return;
        }

        $points = $this->pointService->getPointsForReason('discussion_started');

        $this->pointService->award(
            $event->actor,
            $points,
            'discussion_started',
            $event->discussion->id,
            'discussion'
        );
    }
}
