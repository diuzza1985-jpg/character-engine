<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blocco "Approfondimento" del questionario (facoltativo, solo per utenti autenticati —
        // esplicitamente fuori scope nella consegna precedente, richiesto ora). key_relationships
        // alimenta CharacterRelationship alla conversione (quello narrativo esistente, mai
        // popolato da input utente finora, solo dal motore di vita).
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->json('dietary_habits')->nullable();
            $table->json('hobbies')->nullable();
            $table->text('life_goals')->nullable();
            $table->json('fears')->nullable();
            $table->text('backstory')->nullable();
            $table->json('key_relationships')->nullable();
            $table->json('typical_phrases')->nullable();
            $table->json('hyper_specific_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'dietary_habits', 'hobbies', 'life_goals', 'fears',
                'backstory', 'key_relationships', 'typical_phrases', 'hyper_specific_details',
            ]);
        });
    }
};
