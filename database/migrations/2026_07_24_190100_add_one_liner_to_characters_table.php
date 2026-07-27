<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "In una frase, chi è" dal questionario di creazione (Nucleo, Identità): identità
        // base del personaggio, colonna diretta e non prosa bible, coerente con name/slug.
        Schema::table('characters', function (Blueprint $table) {
            $table->string('one_liner')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('one_liner');
        });
    }
};
