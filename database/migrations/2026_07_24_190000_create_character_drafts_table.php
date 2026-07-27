<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bozza di personaggio compilata da un utente anonimo prima della registrazione
        // (questionario pubblico /crea-personaggio). Nessun vincolo enum a livello DB per
        // humor_level/status, stesso principio già in uso per narrative_format/formato altrove
        // nel progetto: la validazione vive lato applicazione, non nello schema.
        Schema::create('character_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_token')->index();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // Perché esiste (sez. -1 del questionario)
            $table->string('goal')->nullable();
            $table->string('goal_secondary')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('niche')->nullable();

            // Identità + vita quotidiana (accorpata qui nello screen "identity" del wizard)
            $table->string('name')->nullable();
            $table->string('role')->nullable();
            $table->string('one_liner')->nullable();
            $table->string('living_situation')->nullable();
            $table->json('pets')->nullable();
            $table->string('environment')->nullable();

            // Temperamento e valori (screen "personality")
            $table->json('traits')->nullable();
            $table->json('core_values')->nullable();
            $table->json('dislikes')->nullable();

            // Come comunica (screen "voice")
            $table->unsignedTinyInteger('communication_formality')->nullable();
            $table->unsignedTinyInteger('communication_verbosity')->nullable();
            $table->unsignedTinyInteger('communication_directness')->nullable();
            $table->string('emoji_usage')->nullable();

            // Umorismo (condizionale)
            $table->string('humor_level')->nullable();
            $table->json('joke_targets')->nullable();
            $table->json('humor_safe_topics')->nullable();
            $table->json('content_safe_limits')->nullable();

            // Aspetto (nessuna generazione immagine qui, solo testo descrittivo)
            $table->string('age_range')->nullable();
            $table->string('presentation')->nullable();
            $table->string('style_archetype')->nullable();
            $table->string('hair_color')->nullable();
            $table->string('hair_style')->nullable();
            $table->string('eye_color')->nullable();
            $table->string('body_type')->nullable();
            $table->string('nose_detail')->nullable();
            $table->string('mouth_detail')->nullable();
            $table->text('distinguishing_detail')->nullable();

            $table->string('status')->default('in_corso');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_drafts');
    }
};
