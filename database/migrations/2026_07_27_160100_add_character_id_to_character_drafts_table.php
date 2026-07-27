<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collega permanentemente una bozza al Character che ha prodotto: non più un artefatto
        // usa-e-getta pre-registrazione, ma la fonte strutturata canonica riapribile per
        // modificare il personaggio (il questionario resta l'unico modo di farlo, mai editing
        // diretto della prosa bible).
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->foreignId('character_id')->nullable()->unique()->after('tenant_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('character_id');
        });
    }
};
