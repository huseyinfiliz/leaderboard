<?php

/*
 * This file is part of huseyinfiliz/leaderboard.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Leaderboard\Event;

use Flarum\User\User;

/**
 * A member's points total has changed: points were awarded or revoked.
 *
 * Dispatched after the change is committed, so listeners reading
 * leaderboard_user_totals see the new total.
 */
class PointsUpdated
{
    public function __construct(
        public User $user
    ) {
    }
}
