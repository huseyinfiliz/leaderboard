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
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Leaderboard\Service\PointService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PointServiceTest extends TestCase
{
    protected PointService $service;
    protected SettingsRepositoryInterface $settings;
    protected ExtensionManager $extensions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = m::mock(SettingsRepositoryInterface::class);
        $this->extensions = m::mock(ExtensionManager::class);
        $this->extensions->shouldReceive('isEnabled')->andReturn(false)->byDefault();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    #[Test]
    public function test_get_points_for_reason_returns_setting_value(): void
    {
        $this->settings->shouldReceive('get')
            ->with('huseyinfiliz-leaderboard.points_discussion_started', 1)
            ->andReturn('5');

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $this->assertEquals(5, $service->getPointsForReason('discussion_started'));
    }

    #[Test]
    public function test_get_points_for_reason_returns_default_when_not_set(): void
    {
        $this->settings->shouldReceive('get')
            ->with('huseyinfiliz-leaderboard.points_discussion_started', 1)
            ->andReturn(null);

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $this->assertEquals(0, $service->getPointsForReason('discussion_started'));
    }

    #[Test]
    public function test_get_points_for_unknown_reason_returns_zero(): void
    {
        $this->settings->shouldReceive('get')
            ->with('huseyinfiliz-leaderboard.points_unknown_reason', 0)
            ->andReturn(null);

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $this->assertEquals(0, $service->getPointsForReason('unknown_reason'));
    }

    #[Test]
    public function test_get_points_for_downvote_default_is_negative(): void
    {
        $this->settings->shouldReceive('get')
            ->with('huseyinfiliz-leaderboard.points_downvote_received', -1)
            ->andReturn('-1');

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $this->assertEquals(-1, $service->getPointsForReason('downvote_received'));
    }

    #[Test]
    public function test_is_excluded_by_tags_returns_false_when_tags_extension_disabled(): void
    {
        $this->extensions->shouldReceive('isEnabled')
            ->with('flarum-tags')
            ->andReturn(false);

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $discussion = m::mock(\Flarum\Discussion\Discussion::class);

        $this->assertFalse($service->isExcludedByTags($discussion));
    }

    #[Test]
    public function test_is_excluded_by_tags_returns_false_when_no_excluded_tags(): void
    {
        $this->extensions->shouldReceive('isEnabled')
            ->with('flarum-tags')
            ->andReturn(true);

        $this->settings->shouldReceive('get')
            ->with('huseyinfiliz-leaderboard.excluded_tags', '')
            ->andReturn('');

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $discussion = m::mock(\Flarum\Discussion\Discussion::class);

        $this->assertFalse($service->isExcludedByTags($discussion));
    }

    #[Test]
    public function test_is_excluded_by_group_returns_false_when_no_excluded_groups(): void
    {
        $this->settings->shouldReceive('get')
            ->with('huseyinfiliz-leaderboard.excluded_groups', '')
            ->andReturn('');

        $cache = m::mock(\Illuminate\Contracts\Cache\Repository::class);
        $db = m::mock(\Illuminate\Database\ConnectionInterface::class);

        $service = new PointService($this->settings, $cache, $db, $this->extensions);

        $user = m::mock(\Flarum\User\User::class);

        $this->assertFalse($service->isExcludedByGroup($user));
    }
}
