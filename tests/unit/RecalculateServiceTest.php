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

use Flarum\Extension\ExtensionManager;
use HuseyinFiliz\Leaderboard\Service\PointService;
use HuseyinFiliz\Leaderboard\Service\RecalculateService;
use Illuminate\Database\ConnectionInterface;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class RecalculateServiceTest extends TestCase
{
    protected PointService $pointService;
    protected ConnectionInterface $db;
    protected ExtensionManager $extensions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pointService = m::mock(PointService::class);
        $this->db = m::mock(ConnectionInterface::class);
        $this->extensions = m::mock(ExtensionManager::class);

        $this->extensions->shouldReceive('isEnabled')->andReturn(false)->byDefault();
        $this->pointService->shouldReceive('getExcludedGroupIds')->andReturn([])->byDefault();
        $this->pointService->shouldReceive('getExcludedTagIds')->andReturn([])->byDefault();
        $this->db->shouldReceive('getTablePrefix')->andReturn('')->byDefault();
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(m::getContainer()->mockery_getExpectationCount());
        m::close();
        parent::tearDown();
    }

    /** @test */
    public function test_execute_sync_step_returns_correct_metadata(): void
    {
        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);

        // Step 0 — discussion_started
        $this->db->shouldReceive('delete')->once();
        $this->db->shouldReceive('insert')->once();

        $result = $service->executeSyncStep(0);

        $this->assertSame(0, $result['step']);
        $this->assertSame(10, $result['totalSteps']);
        $this->assertSame('discussion_started', $result['stepKey']);
        $this->assertFalse($result['done']);
    }

    /** @test */
    public function test_execute_sync_step_last_step_is_done(): void
    {
        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);

        // Step 9 — rebuild_totals (last step)
        $this->pointService->shouldReceive('buildPointsCaseSql')->once()->andReturn([
            'sql' => 'CASE reason WHEN ? THEN ? ELSE 0 END',
            'bindings' => ['discussion_started', 1],
        ]);

        $totalsTable = m::mock();
        $totalsTable->shouldReceive('delete')->once();
        $this->db->shouldReceive('table')->with('leaderboard_user_totals')->andReturn($totalsTable);
        $this->db->shouldReceive('insert')->once();

        $result = $service->executeSyncStep(9);

        $this->assertSame(9, $result['step']);
        $this->assertSame('rebuild_totals', $result['stepKey']);
        $this->assertTrue($result['done']);
    }

    /** @test */
    public function test_execute_sync_step_beyond_total_returns_done(): void
    {
        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);

        $result = $service->executeSyncStep(99);

        $this->assertTrue($result['done']);
        $this->assertSame('done', $result['stepKey']);
    }

    /** @test */
    public function test_disabled_extension_purges_reason(): void
    {
        $this->extensions->shouldReceive('isEnabled')->with('flarum-likes')->andReturn(false);

        $table = m::mock();
        $table->shouldReceive('where')->with('reason', 'like_received')->andReturnSelf();
        $table->shouldReceive('delete')->once();
        $this->db->shouldReceive('table')->with('leaderboard_points')->andReturn($table);

        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);
        $result = $service->executeSyncStep(2); // like_received step

        $this->assertSame('like_received', $result['stepKey']);
        $this->assertFalse($result['done']);
    }

    /** @test */
    public function test_enabled_extension_syncs_events(): void
    {
        $this->extensions->shouldReceive('isEnabled')->with('flarum-likes')->andReturn(true);
        $this->extensions->shouldReceive('isEnabled')->with('flarum-tags')->andReturn(false);

        // Sync like_received: delete orphans + insert missing
        $this->db->shouldReceive('delete')->once();
        $this->db->shouldReceive('insert')->once();

        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);
        $result = $service->executeSyncStep(2); // like_received step

        $this->assertSame('like_received', $result['stepKey']);
    }

    /** @test */
    public function test_rebuild_totals_uses_points_case_sql(): void
    {
        $this->pointService->shouldReceive('buildPointsCaseSql')->once()->andReturn([
            'sql' => 'CASE reason WHEN ? THEN ? WHEN ? THEN ? ELSE 0 END',
            'bindings' => ['discussion_started', 1, 'post_created', 1],
        ]);

        $totalsTable = m::mock();
        $totalsTable->shouldReceive('delete')->once();
        $this->db->shouldReceive('table')->with('leaderboard_user_totals')->andReturn($totalsTable);

        $this->db->shouldReceive('insert')
            ->withArgs(function ($sql, $bindings = []) {
                return is_string($sql)
                    && str_contains($sql, 'leaderboard_user_totals')
                    && str_contains($sql, 'CASE reason');
            })
            ->once();

        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);
        $service->rebuildTotals();
    }

    /** @test */
    public function test_group_exclusion_sql_is_included_when_groups_excluded(): void
    {
        $this->pointService->shouldReceive('getExcludedGroupIds')->andReturn([3, 4]);

        // Step 0 — discussion_started sync should include group exclusion
        $this->db->shouldReceive('delete')
            ->withArgs(function ($sql) {
                return is_string($sql)
                    && str_contains($sql, 'NOT IN (SELECT user_id FROM group_user WHERE group_id IN (3,4))');
            })
            ->once();

        $this->db->shouldReceive('insert')
            ->withArgs(function ($sql) {
                return is_string($sql)
                    && str_contains($sql, 'NOT IN (SELECT user_id FROM group_user WHERE group_id IN (3,4))');
            })
            ->once();

        $service = new RecalculateService($this->pointService, $this->db, $this->extensions);
        $service->executeSyncStep(0);
    }
}
