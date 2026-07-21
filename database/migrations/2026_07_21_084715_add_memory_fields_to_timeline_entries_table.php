<?php
// database/migrations/xxxx_xx_xx_add_memory_fields_to_timeline_entries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            // 0-1, quanto vale la pena ricordare questo momento mesi dopo.
            // Default = null: se non valorizzato esplicitamente, il selector
            // usa life_events.weight come fallback (vedi TimelineEntry::effectiveMemorability()).
            $table->float('memorability')->nullable()->after('scene');
            $table->unsignedInteger('referenced_count')->default(0)->after('memorability');
            $table->timestamp('last_referenced_at')->nullable()->after('referenced_count');
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->dropColumn(['memorability', 'referenced_count', 'last_referenced_at']);
        });
    }
};