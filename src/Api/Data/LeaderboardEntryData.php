<?php

namespace HuseyinFiliz\Leaderboard\Api\Data;

use Flarum\User\User;

class LeaderboardEntryData
{
    public int $id;
    public int $points;
    public int $rank;
    public ?User $user;

    public function __construct(int $id, int $points, int $rank, ?User $user = null)
    {
        $this->id = $id;
        $this->points = $points;
        $this->rank = $rank;
        $this->user = $user;
    }
}
