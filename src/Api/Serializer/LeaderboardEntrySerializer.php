<?php

namespace HuseyinFiliz\Leaderboard\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\UserSerializer;
use HuseyinFiliz\Leaderboard\Api\Data\LeaderboardEntryData;
use Tobscure\JsonApi\Relationship;

class LeaderboardEntrySerializer extends AbstractSerializer
{
    protected $type = 'leaderboard-entries';

    public function getId($model): string
    {
        return (string) $model->id;
    }

    protected function getDefaultAttributes($model): array
    {
        /** @var LeaderboardEntryData $model */
        return [
            'points' => $model->points,
            'rank' => $model->rank,
        ];
    }

    protected function user(LeaderboardEntryData $model): Relationship
    {
        /** @var LeaderboardEntryData $model */
        return $this->hasOne($model, UserSerializer::class);
    }
}
