<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->unsignedInteger('diario_ogni_n_post')->default(20)->after('max_giorni_silenzio');
        });
    }
    public function down(): void
    {
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->dropColumn('diario_ogni_n_post');
        });
    }
};
