<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suggerite una volta sola da ConvertCharacterDraftToCharacter (chiamata LLM dedicata)
        // alla creazione del personaggio, poi editabili a mano in Filament — checkpoint umano
        // prima dell'uso in produzione, stesso principio già scritto per le reference delle
        // relazioni (character-engine-coerenza-visiva-relazioni.md sez. 3).
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->json('hot_topic_subreddits')->nullable()->after('niche');
        });
    }

    public function down(): void
    {
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->dropColumn('hot_topic_subreddits');
        });
    }
};
