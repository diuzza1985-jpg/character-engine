<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_visual_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->text('age_description')->nullable();
            $table->text('face_description')->nullable();
            $table->text('hair_description')->nullable();
            $table->text('wardrobe_notes')->nullable();
            $table->text('visual_rules_text')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('character_visual_profiles'); }
};
