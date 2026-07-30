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
        // Non è una colonna di "ruolo" (esplicitamente esclusa in Fase 1 del documento
        // mondo-narrativo, sez. 1: nessun characters.role legato a comparsa/pubblicazione, perché
        // quel ruolo può cambiare nel tempo). 'kind' è un fatto tassonomico immutabile — un
        // animale non "diventa" una persona — distinto per principio dallo status/social_account
        // che già governano se un personaggio pubblica. Nullable e non popolato per i personaggi
        // "principali" (Sofia, Fernando creato direttamente): serve solo a distinguere in modo
        // affidabile un Character-animale da un Character-persona quando si propaga un animale
        // al resto della famiglia (altrimenti nessun dato lo permetterebbe con certezza).
        Schema::table('characters', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
