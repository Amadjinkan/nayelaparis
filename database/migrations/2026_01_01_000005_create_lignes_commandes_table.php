<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lignes_commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();

            // Snapshot du produit au moment de la commande (au cas où il change après)
            $table->string('nom_produit', 200);
            $table->decimal('prix_unitaire', 10, 2);
            $table->string('emoji', 10)->nullable();
            $table->string('taille', 50)->nullable();
            $table->integer('quantite');

            $table->decimal('sous_total', 10, 2);              // prix_unitaire * quantite

            $table->timestamps();

            $table->index('commande_id');
            $table->index('produit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_commandes');
    }
};
