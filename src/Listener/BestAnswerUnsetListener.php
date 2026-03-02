<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\BestAnswer\Events\BestAnswerUnset;
use HuseyinFiliz\Leaderboard\Service\PointService;

class BestAnswerUnsetListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(BestAnswerUnset $event): void
    {
        $answerAuthor = $event->post->user;

        if (!$answerAuthor) {
            return;
        }

        $this->pointService->revoke(
            $answerAuthor,
            'best_answer',
            $event->discussion->id,
            'discussion'
        );
    }
}
