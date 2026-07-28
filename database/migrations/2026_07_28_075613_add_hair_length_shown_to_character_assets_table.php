<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guardia minima sul caricamento manuale in Filament (AssetsRelationManager): quale
        // lunghezza dei capelli mostra questa specifica foto, per poter avvisare (non bloccare)
        // se diverge dalla hair_length canonica del personaggio — stesso principio del fix sul
        // wizard, applicato qui alle foto caricate a mano invece che al testo generato.
        Schema::table('character_assets', function (Blueprint $table) {
            $table->string('hair_length_shown')->nullable()->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('character_assets', function (Blueprint $table) {
            $table->dropColumn('hair_length_shown');
        });
    }
};
