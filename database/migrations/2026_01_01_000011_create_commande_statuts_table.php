<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('commandes')) {
            DB::statement("ALTER TABLE commandes MODIFY statut ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending'");
        }

        Schema::create('commande_statuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->string('statut', 40);
            $table->string('label', 80);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['commande_id', 'created_at']);
            $table->index('statut');
        });

        if (Schema::hasTable('commandes')) {
            $now = now();
            DB::table('commandes')
                ->orderBy('id')
                ->select(['id', 'statut', 'created_at'])
                ->chunk(100, function ($commandes) use ($now) {
                    foreach ($commandes as $commande) {
                        DB::table('commande_statuts')->insert([
                            'commande_id' => $commande->id,
                            'statut' => $commande->statut,
                            'label' => $this->label($commande->statut),
                            'note' => 'Historique initial créé automatiquement.',
                            'user_id' => null,
                            'created_at' => $commande->created_at ?: $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commande_statuts');

        if (Schema::hasTable('commandes')) {
            DB::statement("ALTER TABLE commandes MODIFY statut ENUM('pending','paid','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }

    private function label(string $statut): string
    {
        return match ($statut) {
            'paid' => 'Payée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
            'refunded' => 'Remboursée',
            default => 'En attente',
        };
    }
};
