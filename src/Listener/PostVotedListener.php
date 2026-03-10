<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\Gamification\Events\PostWasVoted;
use HuseyinFiliz\Leaderboard\Service\PointService;

class PostVotedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(PostWasVoted $event): void
    {
        $vote = $event->vote;
        $post = $vote->post;
        $recipient = $post->user;
        $voter = $vote->user;

        if (!$recipient || !$voter) {
            return;
        }

        // Don't award if the voter is the post author
        if ($voter->id === $recipient->id) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($recipient)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($post->discussion)) {
            return;
        }

        // First revoke any previous vote points from this voter on this post
        $this->pointService->revoke($recipient, 'upvote_received', $post->id, 'post', $voter->id);
        $this->pointService->revoke($recipient, 'downvote_received', $post->id, 'post', $voter->id);

        if ($vote->value === 1) {
            $points = $this->pointService->getPointsForReason('upvote_received');
            $this->pointService->award($recipient, $points, 'upvote_received', $post->id, 'post', $voter->id);
        } elseif ($vote->value === -1) {
            $points = $this->pointService->getPointsForReason('downvote_received');
            $this->pointService->award($recipient, $points, 'downvote_received', $post->id, 'post', $voter->id);
        }
        // value === 0 means vote removed — we already revoked above
    }
}
