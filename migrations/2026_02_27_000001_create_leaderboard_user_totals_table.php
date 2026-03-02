<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('leaderboard_user_totals', function (Blueprint $table) {
    $table->unsignedInteger('user_id')->primary();
    $table->integer('points_total')->default(0);

    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->index('points_total');
});
