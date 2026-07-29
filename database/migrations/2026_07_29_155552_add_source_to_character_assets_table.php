<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Default 'ai_generated' per le righe già esistenti (Fase 4 mondo narrativo): oggi
        // rappresentano tutte il volto AI del personaggio stesso, non la foto reale di una terza
        // persona — nessuna di loro richiedeva consenso, coerente col significato di questa
        // colonna. Solo un nuovo upload da parte dell'utente finale valorizza 'user_uploaded'
        // (e va sempre accompagnato da una riga asset_upload_consents, vedi quella tabella).
        Schema::table('character_assets', function (Blueprint $table) {
            $table->string('source')->default('ai_generated')->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_assets', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
