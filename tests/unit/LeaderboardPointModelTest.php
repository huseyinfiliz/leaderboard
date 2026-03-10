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

use HuseyinFiliz\Leaderboard\Model\LeaderboardPoint;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LeaderboardPointModelTest extends TestCase
{
    #[Test]
    public function test_table_name_is_correct(): void
    {
        $model = new LeaderboardPoint();

        $this->assertEquals('leaderboard_points', $model->getTable());
    }

    #[Test]
    public function test_timestamps_use_created_at_only(): void
    {
        $model = new LeaderboardPoint();

        $this->assertTrue($model->timestamps);
        $this->assertEquals('created_at', $model::CREATED_AT);
        $this->assertNull($model::UPDATED_AT);
    }

    #[Test]
    public function test_fillable_fields_are_set(): void
    {
        $model = new LeaderboardPoint();

        $this->assertEquals(
            ['user_id', 'reason', 'subject_id', 'subject_type', 'actor_id', 'created_at'],
            $model->getFillable()
        );
    }
}
