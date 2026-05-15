<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('group_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'team_id']);
        });

        Schema::create('match_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('groups')
                ->cascadeOnDelete();
            $table->string('stage')->nullable();
            $table->unsignedTinyInteger('leg')->nullable();
            $table->string('pairing_key')->nullable();
            $table->unsignedTinyInteger('week');
            $table->foreignId('home_team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->foreignId('away_team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('home_score')
                ->nullable();
            $table->unsignedTinyInteger('away_score')
                ->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_games');
        Schema::dropIfExists('group_team');
        Schema::dropIfExists('groups');
    }
};
