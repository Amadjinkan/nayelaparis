<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\Retour;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetourController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    /**
     * GET /api/retours
     * Liste des demandes de retour du client connecté.
     */
    public function mesRetours(Request $request): JsonResponse
    {
        $retours = $request->user()
            ->retours()
            ->with(['commande:id,numero', 'lignes.ligneCommande'])
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'numero_rma' => $r->numero_rma,
                'commande_numero' => $r->commande->numero,
                'motif' => $r->motif,
                'description' => $r->description,
                'statut' => $r->statut,
                'montant_rembourse' => $r->montant_rembourse,
                'date_demande' => $r->created_at->format('d/m/Y'),
                'note_admin' => $r->note_admin,
                'motif_refus' => $r->motif_refus,
                'articles' => $r->lignes->map(fn($l) => [
                    'nom_produit' => $l->ligneCommande->nom_produit,
                    'quantite' => $l->quantite,
                    'montant' => $l->montant,
                ]),
            ]);

        return response()->json($retours);
    }

    /**
     * POST /api/retours
     * Demande de retour pour une commande livrée.
     *
     * Body:
     * {
     *   "commande_id": 12,
     *   "motif": "taille_incorrecte",
     *   "description": "Trop petit pour ma fille",
     *   "articles": [
     *     { "ligne_commande_id": 25, "quantite": 1 },
     *     { "ligne_commande_id": 26, "quantite": 2 }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commande_id' => 'required|integer|exists:commandes,id',
            'motif' => 'required|in:taille_incorrecte,defaut_qualite,non_conforme,recu_endommage,autre',
            'description' => 'required|string|max:2000',
            'note_client' => 'nullable|string|max:1000',
            'articles' => 'required|array|min:1',
            'articles.*.ligne_commande_id' => 'required|integer|exists:lignes_commandes,id',
            'articles.*.quantite' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $commande = Commande::where('id', $data['commande_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Vérifier que la commande peut être retournée
        if (!$commande->peutEtreRetournee()) {
            return response()->json([
                'message' => 'Cette commande ne peut pas être retournée (délai de 30 jours dépassé ou non livrée).',
            ], 422);
        }

        // Vérifier qu'il n'y a pas déjà une demande active pour cette commande
        $existeDeja = Retour::where('commande_id', $commande->id)
            ->whereNotIn('statut', ['refuse', 'clos'])
            ->exists();
        if ($existeDeja) {
            return response()->json([
                'message' => 'Une demande de retour est déjà en cours pour cette commande.',
            ], 422);
        }

        try {
            $retour = DB::transaction(function () use ($commande, $user, $data) {
                $retour = Retour::create([
                    'numero_rma' => Retour::genererNumeroRma(),
                    'commande_id' => $commande->id,
                    'user_id' => $user->id,
                    'motif' => $data['motif'],
                    'description' => $data['description'],
                    'note_client' => $data['note_client'] ?? null,
                    'statut' => 'demande',
                ]);

                foreach ($data['articles'] as $art) {
                    $ligne = LigneCommande::where('id', $art['ligne_commande_id'])
                        ->where('commande_id', $commande->id)
                        ->firstOrFail();

                    if ($art['quantite'] > $ligne->quantite) {
                        throw new \RuntimeException(
                            "Quantité retournée ({$art['quantite']}) supérieure à la quantité commandée ({$ligne->quantite}) pour « {$ligne->nom_produit} »."
                        );
                    }

                    $montant = round($ligne->prix_unitaire * $art['quantite'], 2);

                    $retour->lignes()->create([
                        'ligne_commande_id' => $ligne->id,
                        'quantite' => $art['quantite'],
                        'montant' => $montant,
                    ]);
                }

                return $retour;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Demande de retour créée. Un email de confirmation vous sera envoyé.',
            'retour' => [
                'id' => $retour->id,
                'numero_rma' => $retour->numero_rma,
                'statut' => $retour->statut,
            ],
        ], 201);
    }

    /**
     * GET /api/retours/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $retour = $request->user()
            ->retours()
            ->with(['commande', 'lignes.ligneCommande'])
            ->findOrFail($id);

        return response()->json($retour);
    }

    // ============== ADMIN ==============

    /**
     * GET /api/admin/retours
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Retour::with(['user:id,prenom,nom,email', 'commande:id,numero', 'lignes.ligneCommande']);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * POST /api/admin/retours/{id}/approuver
     */
    public function approuver(Request $request, int $id): JsonResponse
    {
        $retour = Retour::with('lignes')->findOrFail($id);

        if ($retour->statut !== 'demande') {
            return response()->json(['message' => 'Cette demande ne peut plus être approuvée.'], 422);
        }

        $request->validate([
            'note_admin' => 'nullable|string|max:1000',
        ]);

        $retour->update([
            'statut' => 'approuve',
            'approuve_le' => now(),
            'note_admin' => $request->input('note_admin'),
        ]);

        return response()->json([
            'message' => 'Retour approuvé. Le client peut maintenant renvoyer le colis.',
            'retour' => $retour,
        ]);
    }

    /**
     * POST /api/admin/retours/{id}/refuser
     */
    public function refuser(Request $request, int $id): JsonResponse
    {
        $retour = Retour::findOrFail($id);

        $request->validate([
            'motif_refus' => 'required|string|max:1000',
        ]);

        $retour->update([
            'statut' => 'refuse',
            'motif_refus' => $request->input('motif_refus'),
        ]);

        return response()->json(['message' => 'Retour refusé', 'retour' => $retour]);
    }

    /**
     * POST /api/admin/retours/{id}/rembourser
     * Déclenche le remboursement Stripe.
     */
    public function rembourser(Request $request, int $id): JsonResponse
    {
        $retour = Retour::with(['lignes', 'commande.paiement'])->findOrFail($id);

        if (!in_array($retour->statut, ['approuve', 'recu'])) {
            return response()->json([
                'message' => 'Le retour doit être approuvé/reçu avant remboursement.',
            ], 422);
        }

        $montantTotal = $retour->lignes->sum('montant');

        try {
            $refund = $this->stripe->rembourser($retour, (float) $montantTotal);

            return response()->json([
                'message' => "Remboursement de {$montantTotal} CAD effectué.",
                'retour' => $retour->fresh(),
                'stripe_refund_id' => $refund->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
