<?php

namespace HuseyinFiliz\Leaderboard\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends Resource\AbstractResource<object>
 */
class LeaderboardEntryLeanResource extends Resource\AbstractResource implements 
{
    public function type(): string
    {
        return 'leaderboard-entries';
    }

    public function endpoints(): array
    {
        return [
        ];
    }

    public function fields(): array
    {
        return [

            /**
             * @todo migrate logic from old serializer and controllers to this API Resource.
             * @see https://docs.flarum.org/2.x/extend/api#api-resources
             */

            // Example:
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->minLength(3)
                ->maxLength(255)
                ->writable(),


            Schema\Relationship\ToOne::make('user')
                ->includable()
                // ->inverse('?') // the inverse relationship name if any.
                ->type('users'), // the serialized type of this relation (type of the relation model's API resource).
        ];
    }

    public function getId(object $model, OriginalContext $context): string
    {
        return $model->id;
    }
}
