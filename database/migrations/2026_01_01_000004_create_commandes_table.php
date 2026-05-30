<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('numero', 30)->unique();              // ex: NP-2026-000001
            $table->decimal('sous_total', 10, 2);
            $table->decimal('frais_livraison', 10, 2)->default(0);
            $table->decimal('taxes', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('devise', 3)->default('CAD');

            // Statut commande
            $table->enum('statut', [
                'pending',      // En attente de paiement
                'paid',         // Payée
                'processing',   // En préparation
                'shipped',      // Expédiée
                'delivered',    // Livrée
                'cancelled'     // Annulée
            ])->default('pending');

            // Adresse de livraison (snapshot — copiée au moment de la commande)
            $table->string('livr_destinataire')->nullable();
            $table->string('livr_ligne1')->nullable();
            $table->string('livr_ligne2')->nullable();
            $table->string('livr_ville', 100)->nullable();
            $table->string('livr_province', 50)->nullable();
            $table->string('livr_code_postal', 20)->nullable();
            $table->string('livr_pays', 50)->default('Canada');

            // Suivi expédition
            $table->string('numero_suivi', 100)->nullable();
            $table->string('transporteur', 50)->nullable();
            $table->timestamp('date_expedition')->nullable();
            $table->timestamp('date_livraison')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statut']);
            $table->index('statut');
            $table->index('numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
