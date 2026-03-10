<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Likes\Event\PostWasLiked;
use HuseyinFiliz\Leaderboard\Service\PointService;

class PostLikedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(PostWasLiked $event): void
    {
        $postAuthor = $event->post->user;
        $liker = $event->user;

        if (!$postAuthor) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->post->discussion)) {
            return;
        }

        // Award points to post author (skip self-like)
        if ($liker->id !== $postAuthor->id && !$this->pointService->isExcludedByGroup($postAuthor)) {
            $points = $this->pointService->getPointsForReason('like_received');

            $this->pointService->award(
                $postAuthor,
                $points,
                'like_received',
                $event->post->id,
                'post',
                $liker->id
            );
        }

        // Award points to the liker for giving a like
        if (!$this->pointService->isExcludedByGroup($liker)) {
            $points = $this->pointService->getPointsForReason('like_given');

            $this->pointService->award(
                $liker,
                $points,
                'like_given',
                $event->post->id,
                'post',
                $liker->id
            );
        }
    }
}
