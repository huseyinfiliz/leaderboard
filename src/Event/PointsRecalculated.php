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

/**
 * Every member's total has been rebuilt from scratch, e.g. by the admin's
 * recalculation. Anyone could have a new total, so listeners should refresh
 * whatever they derive from the totals.
 */
class PointsRecalculated
{
}
