<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Richiesta utente, 13 ottobre 2026: CheckHotTopics cercava SOLO temi che corrispondono alle
     * hot_topic_keywords del personaggio (sez. judge(): "non un argomento generico... deve
     * essere davvero legato alla sua nicchia") — mai un tema di forte interesse pubblico ma
     * fuori da quella nicchia stretta. Default true: ogni HotTopic già esistente resta marcato
     * come "nella nicchia" (comportamento invariato), solo i nuovi dalla traccia generalista
     * (CheckHotTopics::processGenericCandidates) saranno false.
     */
    public function up(): void
    {
        Schema::table('hot_topics', function (Blueprint $table) {
            $table->boolean('in_niche')->default(true)->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('hot_topics', function (Blueprint $table) {
            $table->dropColumn('in_niche');
        });
    }
};
