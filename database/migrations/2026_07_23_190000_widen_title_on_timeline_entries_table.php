<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nel flusso del cervello editoriale (12.1) "title" prende il valore di
        // decision['idea'], pensato per essere una descrizione interna del contenuto (non un
        // titolo breve come "titolo" nel flusso legacy) — GPT può restituire più di 255
        // caratteri, e non c'è nessuna istruzione nel prompt che lo vincoli a starci dentro
        // (scoperto in produzione: un post reale ha superato il limite ed è andato in errore).
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->text('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('timeline_entries', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }
};
