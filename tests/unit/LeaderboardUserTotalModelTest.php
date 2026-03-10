<?php

/*
 * This file is part of huseyinfiliz/leaderboard.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Leaderboard\Tests\Unit;

use HuseyinFiliz\Leaderboard\Model\LeaderboardUserTotal;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LeaderboardUserTotalModelTest extends TestCase
{
    #[Test]
    public function test_table_name_is_correct(): void
    {
        $model = new LeaderboardUserTotal();

        $this->assertEquals('leaderboard_user_totals', $model->getTable());
    }

    #[Test]
    public function test_primary_key_is_user_id(): void
    {
        $model = new LeaderboardUserTotal();

        $this->assertEquals('user_id', $model->getKeyName());
    }

    #[Test]
    public function test_incrementing_is_disabled(): void
    {
        $model = new LeaderboardUserTotal();

        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function test_timestamps_are_disabled(): void
    {
        $model = new LeaderboardUserTotal();

        $this->assertFalse($model->timestamps);
    }

    #[Test]
    public function test_fillable_fields_are_set(): void
    {
        $model = new LeaderboardUserTotal();

        $this->assertEquals(
            ['user_id', 'points_total'],
            $model->getFillable()
        );
    }
}
