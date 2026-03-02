<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\Reactions\Event\PostWasUnreacted;
use HuseyinFiliz\Leaderboard\Service\PointService;

class PostUnreactedListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(PostWasUnreacted $event): void
    {
        $postAuthor = $event->post->user;
        $reactor = $event->user;

        if ($postAuthor) {
            $this->pointService->revoke(
                $postAuthor,
                'reaction_received',
                $event->post->id,
                'post',
                $reactor->id
            );
        }

        // Revoke reaction_given points from the reactor
        $this->pointService->revoke(
            $reactor,
            'reaction_given',
            $event->post->id,
            'post',
            $reactor->id
        );
    }
}
