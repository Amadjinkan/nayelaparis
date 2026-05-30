<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Table des demandes de retour (RMA = Return Merchandise Authorization)
        Schema::create('retours', function (Blueprint $table) {
            $table->id();
            $table->string('numero_rma', 30)->unique();          // ex: RMA-2026-00001
            $table->foreignId('commande_id')->constrained('commandes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Motif principal du retour
            $table->enum('motif', [
                'taille_incorrecte',  // Mauvaise taille
                'defaut_qualite',     // Défaut produit
                'non_conforme',       // Différent de la description
                'recu_endommage',     // Reçu endommagé
                'autre'
            ]);
            $table->text('description');                           // Détails libres

            // Workflow du retour
            $table->enum('statut', [
                'demande',           // Demande créée par le client
                'approuve',          // Approuvée par l'admin
                'refuse',            // Refusée
                'attendu',           // Étiquette envoyée, on attend le colis
                'recu',              // Colis reçu en entrepôt
                'rembourse',         // Remboursement effectué
                'clos'               // Dossier clos
            ])->default('demande');

            // Remboursement
            $table->decimal('montant_rembourse', 10, 2)->default(0);
            $table->string('stripe_refund_id', 255)->nullable();   // ID du remboursement Stripe

            // Notes
            $table->text('note_client')->nullable();
            $table->text('note_admin')->nullable();
            $table->text('motif_refus')->nullable();

            // Suivi
            $table->string('etiquette_retour', 255)->nullable();   // URL étiquette PDF
            $table->timestamp('approuve_le')->nullable();
            $table->timestamp('recu_le')->nullable();
            $table->timestamp('rembourse_le')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'statut']);
            $table->index('statut');
            $table->index('numero_rma');
        });

        // Articles retournés (un retour peut concerner plusieurs articles)
        Schema::create('lignes_retours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retour_id')->constrained('retours')->cascadeOnDelete();
            $table->foreignId('ligne_commande_id')->constrained('lignes_commandes')->restrictOnDelete();
            $table->integer('quantite');                          // Nombre d'unités retournées
            $table->decimal('montant', 10, 2);                    // Montant à rembourser pour ces unités
            $table->timestamps();

            $table->index('retour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_retours');
        Schema::dropIfExists('retours');
    }
};
