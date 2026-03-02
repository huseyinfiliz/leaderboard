<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\Reactions\Event\PostWasReacted;
use HuseyinFiliz\Leaderboard\Service\PointService;

class PostReactedListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function handle(PostWasReacted $event): void
    {
        $postAuthor = $event->post->user;
        $reactor = $event->user;

        if (!$postAuthor) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->post->discussion)) {
            return;
        }

        // Award points to post author (skip self-reaction)
        if ($reactor->id !== $postAuthor->id && !$this->pointService->isExcludedByGroup($postAuthor)) {
            // Revoke previous reaction from this user on this post first
            $this->pointService->revoke(
                $postAuthor,
                'reaction_received',
                $event->post->id,
                'post',
                $reactor->id
            );

            $points = $this->pointService->getPointsForReason('reaction_received');

            $this->pointService->award(
                $postAuthor,
                $points,
                'reaction_received',
                $event->post->id,
                'post',
                $reactor->id
            );
        }

        // Award points to the reactor for giving a reaction
        if (!$this->pointService->isExcludedByGroup($reactor)) {
            // Revoke previous reaction_given first (handles changed reactions)
            $this->pointService->revoke(
                $reactor,
                'reaction_given',
                $event->post->id,
                'post',
                $reactor->id
            );

            $points = $this->pointService->getPointsForReason('reaction_given');

            $this->pointService->award(
                $reactor,
                $points,
                'reaction_given',
                $event->post->id,
                'post',
                $reactor->id
            );
        }
    }
}
