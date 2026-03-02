<?php

namespace HuseyinFiliz\Leaderboard\Api\Controller;

use Flarum\Http\RequestUtil;
use HuseyinFiliz\Leaderboard\Service\RecalculateService;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RecalculateController implements RequestHandlerInterface
{
    protected RecalculateService $recalculateService;
    protected Cache $cache;

    private const LOCK_KEY = 'leaderboard_recalculating';

    public function __construct(RecalculateService $recalculateService, Cache $cache)
    {
        $this->recalculateService = $recalculateService;
        $this->cache = $cache;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = (array) $request->getParsedBody();
        $action = $body['action'] ?? 'sync-events';

        if ($action === 'rebuild-totals') {
            return $this->handleRebuildTotals();
        }

        return $this->handleSyncEvents($body);
    }

    private function handleRebuildTotals(): ResponseInterface
    {
        $this->recalculateService->rebuildTotals();

        return new JsonResponse(['done' => true]);
    }

    private function handleSyncEvents(array $body): ResponseInterface
    {
        $step = max(0, (int) ($body['step'] ?? 0));

        if ($step === 0 && $this->cache->has(self::LOCK_KEY)) {
            return new JsonResponse([
                'errors' => [[
                    'status' => '409',
                    'code' => 'already_running',
                    'detail' => 'A synchronization is already in progress.',
                ]],
            ], 409);
        }

        if ($step === 0) {
            $this->cache->put(self::LOCK_KEY, true, 600);
        }

        try {
            $result = $this->recalculateService->executeSyncStep($step);
        } catch (\Throwable $e) {
            $this->cache->forget(self::LOCK_KEY);
            throw $e;
        }

        if ($result['done']) {
            $this->cache->forget(self::LOCK_KEY);
        }

        return new JsonResponse($result);
    }
}
