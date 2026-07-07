<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_scene_elements', function (Blueprint $table) {
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_element_id')->constrained()->cascadeOnDelete();
            $table->primary(['character_id', 'scene_element_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('character_scene_elements'); }
};
