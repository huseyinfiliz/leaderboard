<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('leaderboard_points', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->string('reason', 30);
    $table->unsignedInteger('subject_id')->nullable();
    $table->string('subject_type', 20)->nullable();
    $table->unsignedInteger('actor_id')->nullable();
    $table->dateTime('created_at');

    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->index(['user_id', 'created_at']);
    $table->index(['user_id', 'reason']);
    $table->index(['reason', 'subject_id', 'actor_id'], 'lp_reason_subject_actor');
});
