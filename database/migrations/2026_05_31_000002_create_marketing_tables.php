<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('produit_favoris')) {
            Schema::create('produit_favoris', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'produit_id']);
            });
        }

        if (!Schema::hasTable('produit_vues')) {
            Schema::create('produit_vues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
                $table->string('session_id', 120)->nullable();
                $table->timestamp('viewed_at')->useCurrent();
                $table->timestamps();
                $table->index(['user_id', 'viewed_at']);
                $table->index(['session_id', 'viewed_at']);
            });
        }

        if (!Schema::hasTable('produit_avis')) {
            Schema::create('produit_avis', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
                $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
                $table->unsignedTinyInteger('note');
                $table->text('commentaire')->nullable();
                $table->enum('statut', ['pending', 'approved', 'rejected'])->default('approved');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'produit_id']);
                $table->index(['produit_id', 'statut']);
            });
        }

        if (!Schema::hasTable('promotion_codes')) {
            Schema::create('promotion_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('nom', 160);
                $table->enum('type', ['percent', 'fixed', 'free_shipping'])->default('percent');
                $table->decimal('value', 10, 2)->default(0);
                $table->decimal('min_amount', 10, 2)->default(0);
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->unsignedInteger('per_user_limit')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('actif')->default(true);
                $table->timestamps();
                $table->index(['actif', 'starts_at', 'ends_at']);
            });
        }

        if (!Schema::hasTable('loyalty_transactions')) {
            Schema::create('loyalty_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
                $table->integer('points');
                $table->enum('type', ['earned', 'redeemed', 'adjustment'])->default('earned');
                $table->string('label', 180)->nullable();
                $table->timestamps();
                $table->unique(['commande_id', 'type']);
                $table->index(['user_id', 'created_at']);
            });
        }

        Schema::table('commandes', function (Blueprint $table) {
            if (!Schema::hasColumn('commandes', 'promotion_code_id')) {
                $table->foreignId('promotion_code_id')->nullable()->after('devise')->constrained('promotion_codes')->nullOnDelete();
            }
            if (!Schema::hasColumn('commandes', 'code_promo')) {
                $table->string('code_promo', 60)->nullable()->after('promotion_code_id');
            }
            if (!Schema::hasColumn('commandes', 'remise')) {
                $table->decimal('remise', 10, 2)->default(0)->after('code_promo');
            }
            if (!Schema::hasColumn('commandes', 'points_fidelite_gagnes')) {
                $table->unsignedInteger('points_fidelite_gagnes')->default(0)->after('remise');
            }
        });

        foreach ([
            [
                'code' => 'BIENVENUE10',
                'nom' => 'Bienvenue -10%',
                'type' => 'percent',
                'value' => 10,
                'min_amount' => 50,
                'per_user_limit' => 1,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'LIVRAISON',
                'nom' => 'Livraison offerte',
                'type' => 'free_shipping',
                'value' => 0,
                'min_amount' => 75,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ] as $coupon) {
            DB::table('promotion_codes')->updateOrInsert(
                ['code' => $coupon['code']],
                $coupon
            );
        }
    }

    public function down(): void
    {
        //
    }
};
