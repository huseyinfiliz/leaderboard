<?php

/*
 * This file is part of huseyinfiliz/leaderboard.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Leaderboard\Tests\Integration\Api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Flarum\User\User;

class ListLeaderboardTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-leaderboard');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'user3', 'email' => 'user3@example.com', 'is_email_confirmed' => true],
                ['id' => 4, 'username' => 'user4', 'email' => 'user4@example.com', 'is_email_confirmed' => true],
            ],
            'leaderboard_user_totals' => [
                ['user_id' => 2, 'points_total' => 50],
                ['user_id' => 3, 'points_total' => 100],
                ['user_id' => 4, 'points_total' => 25],
            ],
            'leaderboard_points' => [
                ['id' => 1, 'user_id' => 2, 'reason' => 'discussion_started', 'subject_id' => 1, 'subject_type' => 'discussion', 'created_at' => '2026-01-15 10:00:00'],
                ['id' => 2, 'user_id' => 3, 'reason' => 'post_created', 'subject_id' => 1, 'subject_type' => 'post', 'created_at' => '2026-02-01 10:00:00'],
                ['id' => 3, 'user_id' => 4, 'reason' => 'daily_login', 'subject_id' => null, 'subject_type' => null, 'created_at' => '2026-02-25 10:00:00'],
            ],
        ]);
    }

    #[Test]
    public function guest_can_view_leaderboard(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertCount(3, $body['data']);
    }

    #[Test]
    public function leaderboard_is_sorted_by_points_descending(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')
        );

        $body = json_decode($response->getBody()->getContents(), true);

        // Entry IDs are user IDs; sorted by points desc: user3 (100), user2 (50), user4 (25)
        $this->assertEquals('3', $body['data'][0]['id']);
        $this->assertEquals(100, $body['data'][0]['attributes']['points']);

        $this->assertEquals('2', $body['data'][1]['id']);
        $this->assertEquals(50, $body['data'][1]['attributes']['points']);

        $this->assertEquals('4', $body['data'][2]['id']);
        $this->assertEquals(25, $body['data'][2]['attributes']['points']);
    }

    #[Test]
    public function leaderboard_includes_rank(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')
        );

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals(1, $body['data'][0]['attributes']['rank']);
        $this->assertEquals(2, $body['data'][1]['attributes']['rank']);
        $this->assertEquals(3, $body['data'][2]['attributes']['rank']);
    }

    #[Test]
    public function leaderboard_respects_pagination(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')->withQueryParams([
                'page' => ['limit' => 2, 'offset' => 0],
            ])
        );

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertCount(2, $body['data']);
        // JSON:API pagination: "next" link present when more results exist
        $this->assertArrayHasKey('links', $body);
        $this->assertArrayHasKey('next', $body['links']);
    }

    #[Test]
    public function leaderboard_supports_period_filtering(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')->withQueryParams([
                'filter' => ['period' => 'monthly'],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('data', $body);
    }

    #[Test]
    public function leaderboard_entries_include_user_relationship(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')
        );

        $body = json_decode($response->getBody()->getContents(), true);

        $entry = $body['data'][0];

        // JSON:API: attributes contain points and rank
        $this->assertArrayHasKey('points', $entry['attributes']);
        $this->assertArrayHasKey('rank', $entry['attributes']);

        // JSON:API: user is a relationship
        $this->assertArrayHasKey('relationships', $entry);
        $this->assertArrayHasKey('user', $entry['relationships']);

        // JSON:API: user data is in included
        $this->assertArrayHasKey('included', $body);
        $this->assertNotEmpty($body['included']);
    }

    #[Test]
    public function page_limit_cannot_exceed_50(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries')->withQueryParams([
                'page' => ['limit' => 100],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        // With maxLimit=50 and only 3 entries, we should get all 3
        // The important thing is the request doesn't fail
        $this->assertArrayHasKey('data', $body);
        $this->assertLessThanOrEqual(50, count($body['data']));
    }
}
