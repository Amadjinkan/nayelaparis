<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyTransaction;
use App\Models\Produit;
use App\Models\ProduitAvis;
use App\Models\ProduitFavori;
use App\Models\PromotionCode;
use App\Services\MarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function __construct(private MarketingService $marketing) {}

    public function recommended(Request $request): JsonResponse
    {
        return response()->json($this->marketing->recommended(
            $request->user(),
            $request->query('categorie'),
            $request->integer('exclude') ?: null,
            min(12, max(1, $request->integer('limit', 8)))
        ));
    }

    public function similar(int $id): JsonResponse
    {
        $produit = Produit::actif()->findOrFail($id);
        return response()->json($this->marketing->similar($produit));
    }

    public function recordView(Request $request, int $id): JsonResponse
    {
        $produit = Produit::actif()->findOrFail($id);
        $this->marketing->recordView($produit, $request->user(), $request->input('session_id'));

        return response()->json(['message' => 'Vue enregistrée']);
    }

    public function recent(Request $request): JsonResponse
    {
        return response()->json($this->marketing->recentViews($request->user(), $request->query('session_id')));
    }

    public function favorites(Request $request): JsonResponse
    {
        $favoris = ProduitFavori::with('produit')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->pluck('produit')
            ->filter(fn($produit) => $produit && $produit->actif)
            ->values();

        return response()->json($favoris);
    }

    public function addFavorite(Request $request, int $id): JsonResponse
    {
        Produit::actif()->findOrFail($id);

        ProduitFavori::firstOrCreate([
            'user_id' => $request->user()->id,
            'produit_id' => $id,
        ]);

        return response()->json(['message' => 'Favori ajouté']);
    }

    public function removeFavorite(Request $request, int $id): JsonResponse
    {
        ProduitFavori::where('user_id', $request->user()->id)
            ->where('produit_id', $id)
            ->delete();

        return response()->json(['message' => 'Favori retiré']);
    }

    public function reviews(int $id): JsonResponse
    {
        $produit = Produit::actif()->findOrFail($id);
        $avis = ProduitAvis::with('user:id,prenom,nom')
            ->where('produit_id', $produit->id)
            ->where('statut', 'approved')
            ->latest()
            ->get();

        return response()->json([
            'moyenne' => round((float) $avis->avg('note'), 1),
            'total' => $avis->count(),
            'avis' => $avis,
        ]);
    }

    public function addReview(Request $request, int $id): JsonResponse
    {
        Produit::actif()->findOrFail($id);

        $data = $request->validate([
            'note' => 'required|integer|min:1|max:5',
            'commentaire' => 'nullable|string|max:1200',
        ]);

        $avis = ProduitAvis::updateOrCreate(
            ['user_id' => $request->user()->id, 'produit_id' => $id],
            [
                'note' => $data['note'],
                'commentaire' => $data['commentaire'] ?? null,
                'statut' => 'approved',
                'approved_at' => now(),
            ]
        );

        return response()->json(['message' => 'Avis enregistré', 'avis' => $avis]);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:60',
            'subtotal' => 'required|numeric|min:0',
            'shipping' => 'nullable|numeric|min:0',
        ]);

        $result = $this->marketing->calculerPromotion(
            $data['code'],
            (float) $data['subtotal'],
            (float) ($data['shipping'] ?? 0),
            $request->user()
        );

        return response()->json([
            'code' => $result['code'],
            'label' => $result['label'],
            'remise' => $result['remise'],
            'frais_livraison' => $result['frais_livraison'],
        ]);
    }

    public function loyalty(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'points' => $this->marketing->soldeFidelite($user),
            'transactions' => LoyaltyTransaction::where('user_id', $user->id)->latest()->limit(20)->get(),
        ]);
    }

    public function adminCoupons(): JsonResponse
    {
        return response()->json(PromotionCode::latest()->get());
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        $coupon = PromotionCode::create($this->validateCouponPayload($request));
        return response()->json(['message' => 'Coupon créé', 'coupon' => $coupon], 201);
    }

    public function updateCoupon(Request $request, int $id): JsonResponse
    {
        $coupon = PromotionCode::findOrFail($id);
        $coupon->update($this->validateCouponPayload($request, partial: true));

        return response()->json(['message' => 'Coupon mis à jour', 'coupon' => $coupon->fresh()]);
    }

    public function destroyCoupon(int $id): JsonResponse
    {
        PromotionCode::findOrFail($id)->update(['actif' => false]);
        return response()->json(['message' => 'Coupon désactivé']);
    }

    public function adminReviews(): JsonResponse
    {
        return response()->json(ProduitAvis::with(['user:id,prenom,nom,email', 'produit:id,nom'])->latest()->paginate(50));
    }

    public function approveReview(int $id): JsonResponse
    {
        $avis = ProduitAvis::findOrFail($id);
        $avis->update(['statut' => 'approved', 'approved_at' => now()]);

        return response()->json(['message' => 'Avis approuvé', 'avis' => $avis]);
    }

    public function rejectReview(int $id): JsonResponse
    {
        $avis = ProduitAvis::findOrFail($id);
        $avis->update(['statut' => 'rejected']);

        return response()->json(['message' => 'Avis refusé', 'avis' => $avis]);
    }

    private function validateCouponPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'code' => [$required, 'string', 'max:60'],
            'nom' => [$required, 'string', 'max:160'],
            'type' => [$required, 'in:percent,fixed,free_shipping'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'actif' => ['nullable', 'boolean'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}
