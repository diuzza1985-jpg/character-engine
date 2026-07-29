<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quando valorizzata, questa relazione ha un Character reale dietro (bible/profilo
        // visivo/asset propri) — quando null, resta puramente testuale come oggi. Nessuna nuova
        // colonna di "ruolo": un personaggio "secondario" è un Character normale in status
        // draft e senza social_account, non una categoria a parte (documento mondo-narrativo).
        // nullOnDelete, non cascade: se il personaggio collegato viene eliminato, la relazione
        // testuale (name/type/sentiment) resta — torna semplicemente a essere solo narrativa.
        Schema::table('character_relationships', function (Blueprint $table) {
            $table->foreignId('related_character_id')->nullable()->after('character_id')
                ->constrained('characters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('character_relationships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_character_id');
        });
    }
};
