<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('life_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic')->nullable();
            $table->json('scene')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('timeline_entries'); }
};
