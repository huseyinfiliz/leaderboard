<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\Badges\Event\BadgeAwarded;
use HuseyinFiliz\Leaderboard\Service\PointService;

class BadgeAwardedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(BadgeAwarded $event): void
    {
        if ($this->pointService->isExcludedByGroup($event->user)) {
            return;
        }

        $points = $this->pointService->getPointsForReason('badge_earned');

        $this->pointService->award(
            $event->user,
            $points,
            'badge_earned',
            $event->badge->id ?? null,
            'badge'
        );
    }
}
