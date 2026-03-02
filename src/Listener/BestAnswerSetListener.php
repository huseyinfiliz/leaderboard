<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\BestAnswer\Events\BestAnswerSet;
use HuseyinFiliz\Leaderboard\Service\PointService;

class BestAnswerSetListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(BestAnswerSet $event): void
    {
        $answerAuthor = $event->post->user;

        if (!$answerAuthor) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($answerAuthor)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->discussion)) {
            return;
        }

        $points = $this->pointService->getPointsForReason('best_answer');

        $this->pointService->award(
            $answerAuthor,
            $points,
            'best_answer',
            $event->discussion->id,
            'discussion'
        );
    }
}
