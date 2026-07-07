<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('life_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('title');
            $table->unsignedInteger('weight')->default(50);
            $table->unsignedInteger('cooldown_days')->default(0);
            $table->json('scene_hint')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('life_events'); }
};
