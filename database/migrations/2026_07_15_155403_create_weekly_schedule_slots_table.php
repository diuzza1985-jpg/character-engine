<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=domenica ... 6=sabato (Carbon::dayOfWeek)
            $table->time('time_of_day');
            $table->string('post_type'); // image | carousel | story | random
            $table->string('mode'); // random | manual
            $table->foreignId('life_event_id')->nullable()->constrained()->nullOnDelete();
            $table->text('instructions')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('weekly_schedule_slots');
    }
};
