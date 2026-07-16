<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_events', function (Blueprint $table) {
            $table->json('mood_deltas')->nullable()->after('scene_hint');
            $table->boolean('can_start_storyline')->default(false)->after('mood_deltas');
            $table->string('target_relationship')->nullable()->after('can_start_storyline');
        });
    }
    public function down(): void
    {
        Schema::table('life_events', function (Blueprint $table) {
            $table->dropColumn(['mood_deltas', 'can_start_storyline', 'target_relationship']);
        });
    }
};
