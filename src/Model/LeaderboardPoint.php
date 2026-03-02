<?php

namespace HuseyinFiliz\Leaderboard\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $reason
 * @property int|null $subject_id
 * @property string|null $subject_type
 * @property int|null $actor_id
 * @property \Carbon\Carbon $created_at
 */
class LeaderboardPoint extends AbstractModel
{
    protected $table = 'leaderboard_points';

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'reason', 'subject_id', 'subject_type', 'actor_id', 'created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
