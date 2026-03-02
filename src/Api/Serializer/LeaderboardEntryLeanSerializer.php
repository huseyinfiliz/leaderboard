<?php

namespace HuseyinFiliz\Leaderboard\Api\Serializer;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Api\Serializer\BasicUserSerializer;
use HuseyinFiliz\Leaderboard\Api\Data\LeaderboardEntryData;
use Tobscure\JsonApi\Relationship;

class LeaderboardEntryLeanSerializer extends AbstractSerializer
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
        return $this->hasOne($model, BasicUserSerializer::class);
    }
}
