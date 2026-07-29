<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Elenco di persone/animali del mondo del personaggio (documento mondo-narrativo, Fase
        // 2) — diverso da key_relationships/character_relationships (esplicitamente "solo
        // narrativi, non altri personaggi del sistema", vedi hint nello screen approfondimento):
        // ogni voce qui diventa un Character reale in status draft alla conversione
        // (ConvertCharacterDraftToCharacter::syncWorldCharacters()), non solo testo. Ogni item:
        // {tipo: persona|animale, nome, relazione?, descrizione?, specie?, razza?, aspetto?,
        // character_id?} — character_id valorizzato dopo la prima conversione, per
        // l'update-in-place su un resave successivo (mai ricreare lo stesso Character due volte).
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->json('world_characters')->nullable()->after('key_relationships');
        });
    }

    public function down(): void
    {
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->dropColumn('world_characters');
        });
    }
};
