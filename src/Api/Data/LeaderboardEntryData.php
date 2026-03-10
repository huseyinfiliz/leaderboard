<?php

namespace HuseyinFiliz\Leaderboard\Api\Data;

use Flarum\User\User;

class LeaderboardEntryData
{
    public function __construct(public int $id, public int $points, public int $rank, public ?User $user = null)
    {
    }
}
