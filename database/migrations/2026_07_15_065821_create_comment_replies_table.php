<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ig_media_id');
            $table->string('ig_comment_id')->unique();
            $table->string('commenter_username')->nullable();
            $table->text('comment_text')->nullable();
            $table->text('reply_text')->nullable();
            $table->string('ig_reply_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('comment_replies');
    }
};
