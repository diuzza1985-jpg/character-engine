<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Traccia la condivisione automatica in story di un post non-story (image/carousel):
        // nullOnDelete, non cascade — se il post originale viene cancellato la story già
        // pubblicata su Instagram resta un contenuto a sé, non va persa la sua riga.
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('related_post_id')->nullable()->after('generation_id')
                ->constrained('posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_post_id');
        });
    }
};
