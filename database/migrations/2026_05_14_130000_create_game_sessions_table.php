<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mode');
            $table->string('status')->default('setup');
            $table->string('current_stage')->nullable();
            $table->unsignedTinyInteger('current_week')->nullable();
            $table->foreignId('champion_team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('game_session_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')
                ->constrained('game_sessions')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['game_session_id', 'team_id']);
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->nullable()
                ->after('id')
                ->constrained('game_sessions')
                ->cascadeOnDelete();
        });

        Schema::table('match_games', function (Blueprint $table) {
            $table->foreignId('game_session_id')
                ->nullable()
                ->after('id')
                ->constrained('game_sessions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('match_games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_session_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_session_id');
        });

        Schema::dropIfExists('game_session_team');
        Schema::dropIfExists('game_sessions');
    }
};
