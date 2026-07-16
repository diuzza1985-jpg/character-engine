<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->string('decisione');
            $table->text('motivazione')->nullable();
            $table->foreignId('storyline_id')->nullable()->constrained('storylines')->nullOnDelete();
            $table->boolean('used_news')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('editorial_decisions'); }
};
