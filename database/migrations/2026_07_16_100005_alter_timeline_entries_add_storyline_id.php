<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->foreignId('storyline_id')->nullable()->after('life_event_id')->constrained('storylines')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storyline_id');
        });
    }
};
