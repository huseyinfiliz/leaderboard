<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\User\Event\LoggedIn;
use HuseyinFiliz\Leaderboard\Service\PointService;

class DailyLoginListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(LoggedIn $event): void
    {
        if ($this->pointService->isExcludedByGroup($event->user)) {
            return;
        }

        $this->pointService->checkDailyLogin($event->user);
    }
}
