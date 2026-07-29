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
        // Evento auditabile (Fase 4 mondo narrativo, sez. 4 del documento comparse): non solo una
        // spunta nell'interfaccia senza traccia lato server. Una riga per ogni asset caricato
        // dall'utente con source=user_uploaded — chi ha accettato, quando, quale versione esatta
        // del testo. cascadeOnDelete su character_asset: se l'asset viene rimosso non ha senso
        // tenere il record di consenso orfano.
        Schema::create('asset_upload_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('consent_text_version');
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_upload_consents');
    }
};
