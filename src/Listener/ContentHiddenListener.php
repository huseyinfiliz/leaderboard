<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Service\PointService;
use Illuminate\Contracts\Events\Dispatcher;

class ContentHiddenListener
{
    protected PointService $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PostHidden::class, [$this, 'handlePostHidden']);
        $events->listen(DiscussionHidden::class, [$this, 'handleDiscussionHidden']);
    }

    public function handlePostHidden(PostHidden $event): void
    {
        $user = $event->post->user;

        if (!$user) {
            return;
        }

        $this->pointService->revoke(
            $user,
            'post_created',
            $event->post->id,
            'post'
        );

        $this->revokeRelatedPoints($user, $event->post->id);
    }

    public function handleDiscussionHidden(DiscussionHidden $event): void
    {
        $user = $event->discussion->user;

        if (!$user) {
            return;
        }

        $this->pointService->revoke(
            $user,
            'discussion_started',
            $event->discussion->id,
            'discussion'
        );
    }

    protected function revokeRelatedPoints(User $user, int $postId): void
    {
        $this->pointService->revoke($user, 'like_received', $postId);
        $this->pointService->revoke($user, 'reaction_received', $postId);
        $this->pointService->revoke($user, 'upvote_received', $postId);
        $this->pointService->revoke($user, 'downvote_received', $postId);

        // Also revoke like_given and reaction_given via bulk (different users gave them)
        $this->pointService->revokeBulkBySubject('like_given', $postId, 'post');
        $this->pointService->revokeBulkBySubject('reaction_given', $postId, 'post');
    }
}
