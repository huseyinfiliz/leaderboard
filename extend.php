<?php

/*
 * This file is part of huseyinfiliz/leaderboard.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Leaderboard;

use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Discussion\Event as DiscussionEvent;
use Flarum\Extend;
use Flarum\Post\Event as PostEvent;
use Flarum\User\Event as UserEvent;
use Flarum\User\User;

return [
    (new Extend\ServiceProvider())
        ->register(Provider\LeaderboardServiceProvider::class),

    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/leaderboard', 'huseyinfiliz-leaderboard.index'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->get('/leaderboard-entries', 'huseyinfiliz-leaderboard.api.index', Api\Controller\ListLeaderboardController::class)
        ->post('/leaderboard-entries/recalculate', 'huseyinfiliz-leaderboard.api.recalculate', Api\Controller\RecalculateController::class),

    (new Extend\Model(User::class))
        ->hasOne('leaderboardTotal', Model\LeaderboardUserTotal::class, 'user_id'),

    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Integer::make('leaderboardPoints')
                ->get(fn ($user) => $user->leaderboardTotal?->points_total ?? 0),
        ])
        ->endpoint(Endpoint\Show::class, function (Endpoint\Show $endpoint) {
            return $endpoint->eagerLoad(['leaderboardTotal']);
        })
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad(['leaderboardTotal']);
        }),

    (new Extend\ApiResource(Resource\DiscussionResource::class))
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad(['user.leaderboardTotal', 'lastPostedUser.leaderboardTotal', 'mostRelevantPost.user.leaderboardTotal']);
        })
        ->endpoint(Endpoint\Show::class, function (Endpoint\Show $endpoint) {
            return $endpoint->eagerLoad(['posts.user.leaderboardTotal']);
        }),

    (new Extend\ApiResource(Resource\PostResource::class))
        ->endpoint(Endpoint\Index::class, function (Endpoint\Index $endpoint) {
            return $endpoint->eagerLoad(['user.leaderboardTotal']);
        })
        ->endpoint(Endpoint\Show::class, function (Endpoint\Show $endpoint) {
            return $endpoint->eagerLoad(['user.leaderboardTotal']);
        }),

    (new Extend\Settings())
        ->default('huseyinfiliz-leaderboard.leaderboard_name', 'Leaderboard')
        ->default('huseyinfiliz-leaderboard.points_label', 'Points')
        ->default('huseyinfiliz-leaderboard.points_discussion_started', 1)
        ->default('huseyinfiliz-leaderboard.points_post_created', 1)
        ->default('huseyinfiliz-leaderboard.points_daily_login', 1)
        ->default('huseyinfiliz-leaderboard.points_like_received', 1)
        ->default('huseyinfiliz-leaderboard.points_like_given', 0)
        ->default('huseyinfiliz-leaderboard.points_reaction_received', 1)
        ->default('huseyinfiliz-leaderboard.points_reaction_given', 0)
        ->default('huseyinfiliz-leaderboard.points_best_answer', 2)
        ->default('huseyinfiliz-leaderboard.points_badge_earned', 3)
        ->default('huseyinfiliz-leaderboard.points_upvote_received', 1)
        ->default('huseyinfiliz-leaderboard.points_downvote_received', -1)
        ->serializeToForum('huseyinfiliz-leaderboard.leaderboard_name', 'huseyinfiliz-leaderboard.leaderboard_name')
        ->serializeToForum('huseyinfiliz-leaderboard.points_label', 'huseyinfiliz-leaderboard.points_label'),

    // Core event listeners
    (new Extend\Event())
        ->listen(DiscussionEvent\Started::class, Listener\DiscussionStartedListener::class)
        ->listen(PostEvent\Posted::class, Listener\PostCreatedListener::class)
        ->listen(UserEvent\LoggedIn::class, Listener\DailyLoginListener::class)
        ->subscribe(Listener\ContentHiddenListener::class)
        ->subscribe(Listener\ContentDeletedListener::class)
        ->subscribe(Listener\ContentRestoredListener::class),

    // [I1] Optional extension listeners — single chained Conditional
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-likes', fn () => [
            (new Extend\Event())
                ->listen(\Flarum\Likes\Event\PostWasLiked::class, Listener\PostLikedListener::class)
                ->listen(\Flarum\Likes\Event\PostWasUnliked::class, Listener\PostUnlikedListener::class),
        ])
        ->whenExtensionEnabled('fof-reactions', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Reactions\Event\PostWasReacted::class, Listener\PostReactedListener::class)
                ->listen(\FoF\Reactions\Event\PostWasUnreacted::class, Listener\PostUnreactedListener::class),
        ])
        ->whenExtensionEnabled('fof-best-answer', fn () => [
            (new Extend\Event())
                ->listen(\FoF\BestAnswer\Events\BestAnswerSet::class, Listener\BestAnswerSetListener::class)
                ->listen(\FoF\BestAnswer\Events\BestAnswerUnset::class, Listener\BestAnswerUnsetListener::class),
        ])
        ->whenExtensionEnabled('fof-badges', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Badges\Event\BadgeAwarded::class, Listener\BadgeAwardedListener::class),
        ])
        ->whenExtensionEnabled('fof-gamification', fn () => [
            (new Extend\Event())
                ->listen(\FoF\Gamification\Events\PostWasVoted::class, Listener\PostVotedListener::class),
        ]),
];