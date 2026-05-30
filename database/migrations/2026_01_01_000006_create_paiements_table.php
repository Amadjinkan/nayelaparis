<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Identifiants Stripe
            $table->string('stripe_payment_intent_id', 255)->nullable()->unique();
            $table->string('stripe_charge_id', 255)->nullable();
            $table->string('stripe_customer_id', 255)->nullable();

            // Montants
            $table->decimal('montant', 10, 2);
            $table->string('devise', 3)->default('CAD');

            // Détails carte (4 derniers chiffres uniquement, JAMAIS le numéro complet)
            $table->string('marque_carte', 20)->nullable();    // ex: visa, mastercard
            $table->string('quatre_derniers', 4)->nullable();

            // Statut
            $table->enum('statut', [
                'pending',       // En attente
                'processing',    // En traitement
                'succeeded',     // Réussi
                'failed',        // Échec
                'refunded',      // Remboursé
                'partial_refund' // Remboursement partiel
            ])->default('pending');

            $table->text('message_erreur')->nullable();
            $table->json('metadata')->nullable();              // données additionnelles Stripe

            $table->timestamp('paye_le')->nullable();
            $table->timestamps();

            $table->index('stripe_payment_intent_id');
            $table->index(['commande_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
