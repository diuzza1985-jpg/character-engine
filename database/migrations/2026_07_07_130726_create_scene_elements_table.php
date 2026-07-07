<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('rules_text')->nullable();
            $table->text('camera_notes')->nullable();
            $table->text('lighting_notes')->nullable();
            $table->foreignId('image_asset_id')->nullable()->constrained('character_assets')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('scene_elements'); }
};
