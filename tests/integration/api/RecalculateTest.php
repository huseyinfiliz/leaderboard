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

class RecalculateTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-leaderboard');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
        ]);
    }

    /** @test */
    public function guest_cannot_recalculate(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/leaderboard-entries/recalculate')
        );

        $this->assertContains($response->getStatusCode(), [400, 401]);
    }

    /** @test */
    public function normal_user_cannot_recalculate(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/leaderboard-entries/recalculate', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /** @test */
    public function admin_can_sync_events(): void
    {
        $request = $this->request('POST', '/api/leaderboard-entries/recalculate', [
            'authenticatedAs' => 1,
            'json' => ['action' => 'sync-events', 'step' => 0],
        ]);

        $response = $this->send($request);

        $responseBody = $response->getBody()->getContents();
        $this->assertEquals(200, $response->getStatusCode(), "Unexpected response: $responseBody");

        $body = json_decode($responseBody, true);

        $this->assertArrayHasKey('step', $body);
        $this->assertArrayHasKey('totalSteps', $body);
        $this->assertArrayHasKey('stepKey', $body);
        $this->assertArrayHasKey('done', $body);
        $this->assertSame(0, $body['step']);
        $this->assertSame('discussion_started', $body['stepKey']);
        $this->assertFalse($body['done']);
    }

    /** @test */
    public function admin_can_rebuild_totals(): void
    {
        $request = $this->request('POST', '/api/leaderboard-entries/recalculate', [
            'authenticatedAs' => 1,
            'json' => ['action' => 'rebuild-totals'],
        ]);

        $response = $this->send($request);

        $responseBody = $response->getBody()->getContents();
        $this->assertEquals(200, $response->getStatusCode(), "Unexpected response: $responseBody");

        $body = json_decode($responseBody, true);
        $this->assertTrue($body['done']);
    }
}
