<?php

namespace App\Http\Controllers;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\Produit;
use App\Services\EmailNotificationService;
use App\Services\MarketingService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private EmailNotificationService $emails,
        private MarketingService $marketing
    ) {}

    /**
     * GET /api/commandes
     * Liste des commandes du client connecté.
     */
    public function mesCommandes(Request $request): JsonResponse
    {
        $commandes = $request->user()
            ->commandes()
            ->with(['lignes', 'paiement', 'historiqueStatuts'])
            ->latest()
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'date_commande' => $c->created_at->format('d/m/Y'),
                    'total' => $c->total,
                    'remise' => $c->remise ?? 0,
                    'code_promo' => $c->code_promo,
                    'points_fidelite_gagnes' => $c->points_fidelite_gagnes ?? 0,
                    'devise' => $c->devise,
                    'statut' => $c->statut,
                    'statut_label' => Commande::labelStatut($c->statut),
                    'paiement_statut' => $c->paiement?->statut,
                    'numero_suivi' => $c->numero_suivi,
                    'transporteur' => $c->transporteur,
                    'date_expedition' => $c->date_expedition?->format('d/m/Y'),
                    'date_livraison' => $c->date_livraison?->format('d/m/Y'),
                    'peut_etre_retournee' => $c->peutEtreRetournee(),
                    'historique_statuts' => $c->historiqueStatuts->map(fn($h) => [
                        'statut' => $h->statut,
                        'label' => $h->label,
                        'note' => $h->note,
                        'date' => $h->created_at?->format('d/m/Y H:i'),
                    ]),
                    'lignes' => $c->lignes->map(fn($l) => [
                        'id' => $l->id,
                        'produit_id' => $l->produit_id,
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
            ->with(['lignes', 'paiement', 'historiqueStatuts'])
            ->findOrFail($id);

        return response()->json($commande);
    }

    /**
     * GET /api/commandes/{id}/facture
     * Télécharge une facture HTML imprimable pour le client connecté.
     */
    public function facture(Request $request, int $id)
    {
        $commande = $request->user()
            ->commandes()
            ->with(['lignes', 'paiement', 'historiqueStatuts', 'user'])
            ->findOrFail($id);

        $filename = 'facture-' . $commande->numero . '.html';

        return response($this->renderFactureHtml($commande), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
            'code_promo' => 'nullable|string|max:60',
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

                // Frais, promotion & taxes (exemple simplifié : taxes 13% Ontario)
                $fraisLivraison = $sousTotal >= 150 ? 0 : 10;
                $promotion = $this->marketing->calculerPromotion($data['code_promo'] ?? null, $sousTotal, $fraisLivraison, $user);
                $remise = (float) $promotion['remise'];
                $fraisLivraison = (float) $promotion['frais_livraison'];
                $montantTaxable = max(0, $sousTotal - $remise);
                $taxes = round($montantTaxable * 0.13, 2);
                $total = round($montantTaxable + $taxes + $fraisLivraison, 2);

                // Créer la commande
                $commande = Commande::create([
                    'user_id' => $user->id,
                    'numero' => Commande::genererNumero(),
                    'sous_total' => $sousTotal,
                    'frais_livraison' => $fraisLivraison,
                    'taxes' => $taxes,
                    'total' => $total,
                    'devise' => 'CAD',
                    'promotion_code_id' => $promotion['promotion']?->id,
                    'code_promo' => $promotion['code'],
                    'remise' => $remise,
                    'statut' => Commande::STATUT_PENDING,
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

                $commande->changerStatut(
                    Commande::STATUT_PENDING,
                    'Commande créée, en attente du paiement.',
                    $user->id
                );

                $this->marketing->marquerPromotionUtilisee($promotion['promotion']);

                return $commande;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $commande->loadMissing(['lignes', 'user']);
        $this->emails->newOrderForAdmin($commande);
        $this->emails->orderConfirmation($commande);

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

    private function renderFactureHtml(Commande $commande): string
    {
        $fmt = fn($value) => number_format((float) $value, 2, '.', ' ') . ' ' . e($commande->devise ?: 'CAD');
        $date = $commande->created_at?->format('d/m/Y') ?? date('d/m/Y');
        $client = trim(($commande->user?->prenom ?? '') . ' ' . ($commande->user?->nom ?? '')) ?: $commande->livr_destinataire;
        $adresse = array_filter([
            $commande->livr_destinataire,
            $commande->livr_ligne1,
            $commande->livr_ligne2,
            trim(($commande->livr_ville ?: '') . ', ' . ($commande->livr_province ?: '') . ' ' . ($commande->livr_code_postal ?: '')),
            $commande->livr_pays,
        ]);

        $lignes = $commande->lignes->map(function ($ligne) use ($fmt) {
            return '<tr>'
                . '<td>' . e($ligne->nom_produit) . '<br><small>Taille : ' . e($ligne->taille ?: 'Unique') . '</small></td>'
                . '<td>' . (int) $ligne->quantite . '</td>'
                . '<td>' . $fmt($ligne->prix_unitaire) . '</td>'
                . '<td>' . $fmt($ligne->sous_total) . '</td>'
                . '</tr>';
        })->implode('');

        $historique = $commande->historiqueStatuts->map(function ($item) {
            return '<li><strong>' . e($item->label) . '</strong> — '
                . e($item->created_at?->format('d/m/Y H:i') ?? '')
                . ($item->note ? '<br><span>' . e($item->note) . '</span>' : '')
                . '</li>';
        })->implode('');

        return '<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Facture ' . e($commande->numero) . '</title>
  <style>
    body{font-family:Arial,sans-serif;color:#1a1a18;margin:40px;background:#faf8f4}
    .invoice{max-width:860px;margin:auto;background:#fff;padding:44px;border:1px solid #e5dccd}
    h1{font-family:Georgia,serif;font-weight:400;font-size:34px;margin:0}
    .muted{color:#777;font-size:13px;line-height:1.7}.head{display:flex;justify-content:space-between;gap:30px;border-bottom:1px solid #e5dccd;padding-bottom:26px;margin-bottom:28px}
    .brand{letter-spacing:.14em;text-transform:uppercase;font-size:13px;color:#a98238}.num{font-size:14px;text-align:right}
    table{width:100%;border-collapse:collapse;margin:28px 0}th{background:#f5efe5;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#7a6a52}
    th,td{padding:13px;border-bottom:1px solid #eee}td:last-child,th:last-child{text-align:right}small{color:#777}
    .totals{margin-left:auto;width:320px}.totals div{display:flex;justify-content:space-between;padding:8px 0}.grand{font-size:20px;font-weight:700;border-top:1px solid #d6c6ad;margin-top:8px;padding-top:12px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:26px}.history{margin-top:30px}.history li{margin-bottom:10px;color:#777}.history strong{color:#1a1a18}
    @media print{body{background:#fff;margin:0}.invoice{border:none}}
  </style>
</head>
<body>
  <main class="invoice">
    <div class="head">
      <div>
        <div class="brand">NayeLa Paris</div>
        <h1>Facture</h1>
        <p class="muted">Merci pour votre confiance.</p>
      </div>
      <div class="num">
        <strong>' . e($commande->numero) . '</strong><br>
        Date : ' . e($date) . '<br>
        Statut : ' . e(Commande::labelStatut($commande->statut)) . '
      </div>
    </div>
    <div class="grid">
      <section><strong>Client</strong><p class="muted">' . e($client) . '<br>' . e($commande->user?->email ?? '') . '</p></section>
      <section><strong>Livraison</strong><p class="muted">' . implode('<br>', array_map(fn($line) => e($line), $adresse)) . '</p></section>
    </div>
    <table>
      <thead><tr><th>Article</th><th>Qté</th><th>Prix</th><th>Total</th></tr></thead>
      <tbody>' . $lignes . '</tbody>
    </table>
    <div class="totals">
      <div><span>Sous-total</span><strong>' . $fmt($commande->sous_total) . '</strong></div>
      ' . ((float) ($commande->remise ?? 0) > 0 ? '<div><span>Remise ' . e($commande->code_promo ?: '') . '</span><strong>-' . $fmt($commande->remise) . '</strong></div>' : '') . '
      <div><span>Livraison</span><strong>' . $fmt($commande->frais_livraison) . '</strong></div>
      <div><span>Taxes</span><strong>' . $fmt($commande->taxes) . '</strong></div>
      <div class="grand"><span>Total</span><strong>' . $fmt($commande->total) . '</strong></div>
    </div>
    <section class="history">
      <strong>Historique de la commande</strong>
      <ul>' . $historique . '</ul>
    </section>
  </main>
</body>
</html>';
    }
}
