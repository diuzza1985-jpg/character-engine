<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Affianca hot_topic_subreddits (non lo sostituisce — quello resta popolato e dormiente
        // per quando/se arriverà l'approvazione OAuth Reddit): queste sono parole chiave per il
        // match contro il pool condiviso di video di tendenza YouTube (character:check-hot-topics),
        // suggerite una volta sola alla creazione del personaggio ed editabili a mano in Filament,
        // stesso checkpoint umano già in uso per hot_topic_subreddits.
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->json('hot_topic_keywords')->nullable()->after('hot_topic_subreddits');
        });
    }

    public function down(): void
    {
        Schema::table('character_editorial_settings', function (Blueprint $table) {
            $table->dropColumn('hot_topic_keywords');
        });
    }
};
