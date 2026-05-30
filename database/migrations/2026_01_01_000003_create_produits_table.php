<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 200);
            $table->string('categorie', 100);                  // ex: Robes, Costumes, Accessoires
            $table->decimal('prix', 10, 2);                    // ex: 89.00
            $table->integer('stock')->default(0);
            $table->string('tailles', 200)->default('Unique'); // ex: "2 ans, 4 ans, 6 ans"
            $table->text('description')->nullable();
            $table->string('emoji', 10)->default('👗');         // visuel par défaut
            $table->string('image', 255)->nullable();          // chemin image future
            $table->boolean('featured')->default(false);       // mis en avant ?
            $table->boolean('actif')->default(true);           // visible boutique ?
            $table->timestamps();

            $table->index('categorie');
            $table->index('featured');
            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
