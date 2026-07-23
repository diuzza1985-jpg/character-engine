<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('weekly_schedule_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('post_type'); // image | carousel | story | random
            $table->string('mode'); // random | manual
            $table->foreignId('life_event_id')->nullable()->constrained()->nullOnDelete();
            $table->text('instructions')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('status')->default('pending'); // pending|processing|generated|published|failed
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
