<?php

namespace HuseyinFiliz\Leaderboard\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use HuseyinFiliz\Leaderboard\Service\PointService;
use HuseyinFiliz\Leaderboard\Service\RecalculateService;

class LeaderboardServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(PointService::class);
        $this->container->singleton(RecalculateService::class);
    }
}
