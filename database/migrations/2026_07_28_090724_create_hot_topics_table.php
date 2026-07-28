<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Popolata da character:check-hot-topics quando un tema supera il filtro a due stadi
        // (documento temi-attualità, sez. 3.C): "disponibilità per il prossimo ciclo editoriale",
        // stesso principio di $diarioDisponibile ma con contenuto vero da passare al cervello
        // editoriale (titolo/sintesi), non solo un booleano — serve quindi una riga persistita,
        // non un valore calcolato al volo.
        Schema::create('hot_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->string('source_label')->nullable();
            $table->string('source_url')->nullable();
            // pending: in attesa che il cervello editoriale la scelga o che scada.
            // used: scelta e pubblicata (EditorialCycleService::run()).
            // expired: mai scelta entro la finestra di rilevanza (character:check-hot-topics).
            $table->string('status')->default('pending');
            $table->timestamp('discovered_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hot_topics');
    }
};
