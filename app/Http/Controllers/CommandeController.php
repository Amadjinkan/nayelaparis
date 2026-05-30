<?php

namespace App\Http\Controllers;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\Produit;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    /**
     * GET /api/commandes
     * Liste des commandes du client connecté.
     */
    public function mesCommandes(Request $request): JsonResponse
    {
        $commandes = $request->user()
            ->commandes()
            ->with('lignes')
            ->latest()
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'date_commande' => $c->created_at->format('d/m/Y'),
                    'total' => $c->total,
                    'devise' => $c->devise,
                    'statut' => $c->statut,
                    'numero_suivi' => $c->numero_suivi,
                    'transporteur' => $c->transporteur,
                    'date_livraison' => $c->date_livraison?->format('d/m/Y'),
                    'peut_etre_retournee' => $c->peutEtreRetournee(),
                    'lignes' => $c->lignes->map(fn($l) => [
                        'id' => $l->id,
                        'nom_produit' => $l->nom_produit,
                        'prix_unitaire' => $l->prix_unitaire,
                        'quantite' => $l->quantite,
                        'taille' => $l->taille,
                        'emoji' => $l->emoji,
                        'sous_total' => $l->sous_total,
                    ]),
                ];
            });

        return response()->json($commandes);
    }

    /**
     * GET /api/commandes/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $commande = $request->user()
            ->commandes()
            ->with(['lignes', 'paiement'])
            ->findOrFail($id);

        return response()->json($commande);
    }

    /**
     * POST /api/commandes
     * Crée une nouvelle commande à partir du panier.
     * Retourne aussi le client_secret Stripe pour le paiement.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'articles' => 'required|array|min:1',
            'articles.*.produit_id' => 'required|integer|exists:produits,id',
            'articles.*.quantite' => 'required|integer|min:1',
            'articles.*.taille' => 'nullable|string|max:50',
            'adresse_id' => 'nullable|integer|exists:adresses,id',
        ]);

        $user = $request->user();

        // Récupérer l'adresse de livraison
        $adresse = null;
        if (!empty($data['adresse_id'])) {
            $adresse = Adresse::where('user_id', $user->id)
                ->where('id', $data['adresse_id'])
                ->first();
        } else {
            $adresse = $user->adresses()->where('par_defaut', true)->first()
                ?? $user->adresses()->first();
        }

        // Transaction : vérification stock + création commande + lignes + paiement
        try {
            $commande = DB::transaction(function () use ($data, $user, $adresse) {
                $sousTotal = 0;
                $lignes = [];

                // Verrouillage des produits pour éviter sur-réservation
                foreach ($data['articles'] as $article) {
                    $produit = Produit::lockForUpdate()->find($article['produit_id']);

                    if (!$produit || !$produit->actif) {
                        throw new \RuntimeException("Produit indisponible : ID {$article['produit_id']}");
                    }
                    if ($produit->stock < $article['quantite']) {
                        throw new \RuntimeException("Stock insuffisant pour « {$produit->nom} » (reste {$produit->stock})");
                    }

                    $ligneTotal = (float) $produit->prix * (int) $article['quantite'];
                    $sousTotal += $ligneTotal;

                    $lignes[] = [
                        'produit' => $produit,
                        'quantite' => $article['quantite'],
                        'taille' => $article['taille'] ?? null,
                        'sous_total' => $ligneTotal,
                    ];

                    // Décrémenter le stock
                    $produit->decrement('stock', $article['quantite']);
                }

                // Frais & taxes (exemple simplifié : taxes 13% Ontario, livraison 10 CAD si < 150)
                $taxes = round($sousTotal * 0.13, 2);
                $fraisLivraison = $sousTotal >= 150 ? 0 : 10;
                $total = round($sousTotal + $taxes + $fraisLivraison, 2);

                // Créer la commande
                $commande = Commande::create([
                    'user_id' => $user->id,
                    'numero' => Commande::genererNumero(),
                    'sous_total' => $sousTotal,
                    'frais_livraison' => $fraisLivraison,
                    'taxes' => $taxes,
                    'total' => $total,
                    'devise' => 'CAD',
                    'statut' => 'pending',
                    'livr_destinataire' => $user->prenom . ' ' . $user->nom,
                    'livr_ligne1' => $adresse?->ligne1,
                    'livr_ligne2' => $adresse?->ligne2,
                    'livr_ville' => $adresse?->ville,
                    'livr_province' => $adresse?->province,
                    'livr_code_postal' => $adresse?->code_postal,
                    'livr_pays' => $adresse?->pays ?? 'Canada',
                ]);

                // Créer les lignes
                foreach ($lignes as $l) {
                    $commande->lignes()->create([
                        'produit_id' => $l['produit']->id,
                        'nom_produit' => $l['produit']->nom,
                        'prix_unitaire' => $l['produit']->prix,
                        'emoji' => $l['produit']->emoji,
                        'taille' => $l['taille'],
                        'quantite' => $l['quantite'],
                        'sous_total' => $l['sous_total'],
                    ]);
                }

                return $commande;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Créer le PaymentIntent Stripe pour permettre le paiement
        try {
            $intent = $this->stripe->creerPaymentIntent($commande);
        } catch (\Throwable $e) {
            \Log::error('Erreur création PaymentIntent', ['msg' => $e->getMessage()]);
            return response()->json([
                'message' => 'Commande créée. Le paiement doit être relancé.',
                'commande' => $commande->load('lignes'),
                'paiement' => null,
                'payment_error' => $e->getMessage(),
            ], 201);
        }

        return response()->json([
            'message' => 'Commande créée',
            'commande' => $commande->load('lignes'),
            'paiement' => $intent,
        ], 201);
    }
}
