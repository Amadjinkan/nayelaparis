<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\LoyaltyTransaction;
use App\Models\Produit;
use App\Models\ProduitVue;
use App\Models\PromotionCode;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketingService
{
    public function recommended(?User $user = null, ?string $category = null, ?int $excludeId = null, int $limit = 8): Collection
    {
        $query = Produit::actif()->enStock();

        if ($category) {
            $query->where('categorie', $category);
        }
        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->orderByDesc('featured')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function similar(Produit $produit, int $limit = 4): Collection
    {
        return Produit::actif()
            ->enStock()
            ->where('id', '<>', $produit->id)
            ->where('categorie', $produit->categorie)
            ->orderByDesc('featured')
            ->orderBy('prix')
            ->limit($limit)
            ->get();
    }

    public function recordView(Produit $produit, ?User $user = null, ?string $sessionId = null): void
    {
        ProduitVue::create([
            'user_id' => $user?->id,
            'produit_id' => $produit->id,
            'session_id' => $sessionId ? Str::limit($sessionId, 120, '') : null,
            'viewed_at' => now(),
        ]);
    }

    public function recentViews(?User $user = null, ?string $sessionId = null, int $limit = 8): Collection
    {
        $views = ProduitVue::query()
            ->with('produit')
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->when(!$user && $sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->latest('viewed_at')
            ->limit(30)
            ->get();

        return $views
            ->pluck('produit')
            ->filter(fn($produit) => $produit && $produit->actif)
            ->unique('id')
            ->take($limit)
            ->values();
    }

    public function calculerPromotion(?string $code, float $subtotal, float $shipping, ?User $user = null): array
    {
        if (!$code) {
            return [
                'promotion' => null,
                'code' => null,
                'remise' => 0.0,
                'frais_livraison' => $shipping,
                'label' => null,
            ];
        }

        $promotion = PromotionCode::where('code', strtoupper(trim($code)))->first();
        if (!$promotion || !$promotion->isUsable($subtotal, $user)) {
            throw new \RuntimeException('Code promotionnel invalide ou expiré.');
        }

        $discount = $promotion->discountFor($subtotal);
        $finalShipping = $promotion->type === 'free_shipping' ? 0.0 : $shipping;

        return [
            'promotion' => $promotion,
            'code' => $promotion->code,
            'remise' => $discount,
            'frais_livraison' => $finalShipping,
            'label' => $promotion->nom,
        ];
    }

    public function marquerPromotionUtilisee(?PromotionCode $promotion): void
    {
        if ($promotion) {
            $promotion->increment('used_count');
        }
    }

    public function crediterFidelite(Commande $commande): int
    {
        if (!$commande->user_id || LoyaltyTransaction::where('commande_id', $commande->id)->where('type', 'earned')->exists()) {
            return (int) $commande->points_fidelite_gagnes;
        }

        $points = max(1, (int) floor((float) $commande->total));

        LoyaltyTransaction::create([
            'user_id' => $commande->user_id,
            'commande_id' => $commande->id,
            'points' => $points,
            'type' => 'earned',
            'label' => 'Commande ' . $commande->numero,
        ]);

        $commande->forceFill(['points_fidelite_gagnes' => $points])->save();

        return $points;
    }

    public function soldeFidelite(User $user): int
    {
        return (int) LoyaltyTransaction::where('user_id', $user->id)->sum('points');
    }
}
