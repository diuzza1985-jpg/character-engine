<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->float('mood_energia')->default(0.6);
            $table->float('mood_valenza')->default(0);
            $table->float('mood_stress')->default(0.3);
            $table->float('baseline_energia')->default(0.6);
            $table->float('baseline_valenza')->default(0);
            $table->float('baseline_stress')->default(0.3);
            $table->json('drives')->nullable();
            $table->timestamp('last_tick_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('character_state'); }
};
