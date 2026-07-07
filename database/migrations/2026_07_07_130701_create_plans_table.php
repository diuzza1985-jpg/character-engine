<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price_monthly', 8, 2)->default(0);
            $table->unsignedInteger('credits_included')->default(0);
            $table->unsignedInteger('max_characters')->default(1);
            $table->unsignedInteger('max_generations_per_day')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('plans'); }
};
