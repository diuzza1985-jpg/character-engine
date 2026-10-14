<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 14 ottobre 2026: sostituisce la routine cloud "Osservazione bersagli comici" (mai riuscita
     * a girare in 11 settimane: graph.instagram.com bloccato dalla policy di rete dell'ambiente
     * cloud, token IG copiato a mano nel prompt e quindi destinato a scadere). Le caption
     * pubblicate sono già qui nel DB: CheckComicTargets le classifica una volta sola e salva il
     * risultato sul post — comic_target_checked_at distingue "già analizzato" da "mai visto",
     * comic_target resta null anche per i post analizzati che non sono una battuta.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('comic_target')->nullable()->after('caption');
            $table->timestamp('comic_target_checked_at')->nullable()->after('comic_target');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['comic_target', 'comic_target_checked_at']);
        });
    }
};
