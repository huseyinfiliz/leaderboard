<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Likes\Event\PostWasUnliked;
use HuseyinFiliz\Leaderboard\Service\PointService;

class PostUnlikedListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(PostWasUnliked $event): void
    {
        $postAuthor = $event->post->user;
        $liker = $event->user;

        if ($postAuthor) {
            $this->pointService->revoke(
                $postAuthor,
                'like_received',
                $event->post->id,
                'post',
                $liker->id
            );
        }

        // Revoke like_given points from the liker
        $this->pointService->revoke(
            $liker,
            'like_given',
            $event->post->id,
            'post',
            $liker->id
        );
    }
}
