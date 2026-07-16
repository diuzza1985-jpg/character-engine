<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storylines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('dormiente');
            $table->float('importance')->default(0.5);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('storylines'); }
};
