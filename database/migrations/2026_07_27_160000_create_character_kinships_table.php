<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collegamento reale tra due Character (non narrativo come CharacterRelationship, che
        // resta per persone/animali mai modellati come personaggi a sé). relationship_type è
        // sempre dal punto di vista di "character_id" (es. Sofia->Fernando "marito") — nessun
        // vincolo enum a livello DB, stesso principio già in uso altrove nel progetto
        // (narrative_format/formato): la validazione contro l'elenco fisso vive lato app.
        Schema::create('character_kinships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('relationship_type');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['character_id', 'related_character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_kinships');
    }
};
