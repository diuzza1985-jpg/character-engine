<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // hair_style era finora un chip-row unico a selezione singola che mescolava lunghezza
    // (Corti/Medi/Lunghi) e texture/acconciatura (Ricci/Raccolti/Rasati-calvo) — bug reale
    // trovato indagando l'incoerenza capelli di Sofia/Paolo: chi sceglieva una texture lasciava
    // la lunghezza completamente non specificata.
    private const LENGTH_VALUES = ['Corti', 'Medi', 'Lunghi'];

    public function up(): void
    {
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->string('hair_length')->nullable()->after('hair_color');
        });

        // Le bozze esistenti con un valore di lunghezza finito nel campo sbagliato vanno
        // spostate nella nuova colonna: non c'è modo di dedurre una texture da un valore che era
        // solo lunghezza, quindi hair_style resta vuoto per queste righe.
        DB::table('character_drafts')
            ->whereIn('hair_style', self::LENGTH_VALUES)
            ->get(['id', 'hair_style'])
            ->each(function ($row) {
                DB::table('character_drafts')->where('id', $row->id)->update([
                    'hair_length' => $row->hair_style,
                    'hair_style' => null,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('character_drafts', function (Blueprint $table) {
            $table->dropColumn('hair_length');
        });
    }
};
