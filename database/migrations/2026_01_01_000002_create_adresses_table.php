<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->default('Domicile');     // ex: Domicile, Bureau
            $table->string('ligne1');                              // n°, rue
            $table->string('ligne2')->nullable();                  // appt, étage
            $table->string('ville', 100);
            $table->string('province', 50);                        // ex: Ontario, Québec
            $table->string('code_postal', 20);
            $table->string('pays', 50)->default('Canada');
            $table->boolean('par_defaut')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'par_defaut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adresses');
    }
};
