<?php

namespace HuseyinFiliz\Leaderboard\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $points_total
 */
class LeaderboardUserTotal extends AbstractModel
{
    protected $table = 'leaderboard_user_totals';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'points_total'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
