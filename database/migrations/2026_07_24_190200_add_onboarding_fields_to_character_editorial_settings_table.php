<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Obiettivo/pubblico/nicchia dal questionario di creazione (Nucleo, sez. -1): sono
        // configurazione strategica per-personaggio, non prosa bible — stessa tabella di
        // max_giorni_silenzio/diario_ogni_n_post, non una satellite a parte (decisione del
        // piano "questionario pubblico di creazione personaggio"). "niche" in particolare deve
        // restare leggibile da GenerateNewsStoryJob come filtro sulle notizie rilevanti.
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->string('goal')->nullable()->after('character_id');
            $table->string('goal_secondary')->nullable()->after('goal');
            $table->json('target_audience')->nullable()->after('goal_secondary');
            $table->json('niche')->nullable()->after('target_audience');
        });
    }

    public function down(): void
    {
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->dropColumn(['goal', 'goal_secondary', 'target_audience', 'niche']);
        });
    }
};
