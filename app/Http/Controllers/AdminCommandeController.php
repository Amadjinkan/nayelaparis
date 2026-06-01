<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Services\EmailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommandeController extends Controller
{
    public function __construct(private EmailNotificationService $emails) {}

    /**
     * GET /api/admin/commandes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with(['user:id,prenom,nom,email', 'lignes', 'paiement', 'historiqueStatuts']);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->latest()->paginate(50));
    }

    /**
     * PUT /api/admin/commandes/{id}
     * Met à jour le statut, l'expédition, etc.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $commande = Commande::findOrFail($id);
        $ancienStatut = $commande->statut;

        $data = $request->validate([
            'statut' => 'sometimes|in:' . implode(',', Commande::STATUTS),
            'numero_suivi' => 'sometimes|nullable|string|max:100',
            'transporteur' => 'sometimes|nullable|string|max:50',
            'date_expedition' => 'sometimes|nullable|date',
            'date_livraison' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ]);

        $nouveauStatut = $data['statut'] ?? null;
        unset($data['statut']);

        // Si on marque comme expédiée, mettre la date par défaut
        if ($nouveauStatut === Commande::STATUT_SHIPPED && empty($data['date_expedition'])) {
            $data['date_expedition'] = now();
        }
        if ($nouveauStatut === Commande::STATUT_DELIVERED && empty($data['date_livraison'])) {
            $data['date_livraison'] = now();
        }

        if ($data) {
            $commande->update($data);
        }

        if ($nouveauStatut) {
            $commande->changerStatut(
                $nouveauStatut,
                $data['notes'] ?? 'Statut mis à jour depuis le panneau administrateur.',
                $request->user()?->id
            );

            if ($ancienStatut !== $nouveauStatut) {
                $commandePourEmail = $commande->fresh(['lignes', 'user']);

                if ($nouveauStatut === Commande::STATUT_SHIPPED) {
                    $this->emails->shipmentConfirmation($commandePourEmail);
                }

                if ($nouveauStatut === Commande::STATUT_DELIVERED) {
                    $this->emails->deliveryConfirmation($commandePourEmail);
                }
            }
        }

        return response()->json([
            'message' => 'Commande mise à jour',
            'commande' => $commande->fresh(['lignes', 'user', 'paiement', 'historiqueStatuts']),
        ]);
    }

    /**
     * GET /api/admin/statistiques
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_commandes' => Commande::count(),
            'en_attente' => Commande::where('statut', Commande::STATUT_PENDING)->count(),
            'payees' => Commande::whereIn('statut', [Commande::STATUT_PAID, Commande::STATUT_PROCESSING, Commande::STATUT_SHIPPED, Commande::STATUT_DELIVERED])->count(),
            'annulees' => Commande::where('statut', Commande::STATUT_CANCELLED)->count(),
            'remboursees' => Commande::where('statut', Commande::STATUT_REFUNDED)->count(),
            'chiffre_affaire' => Commande::whereIn('statut', [Commande::STATUT_PAID, Commande::STATUT_PROCESSING, Commande::STATUT_SHIPPED, Commande::STATUT_DELIVERED])
                ->sum('total'),
            'commandes_recentes' => Commande::with('user:id,prenom,nom')
                ->latest()
                ->take(5)
                ->get(['id', 'numero', 'user_id', 'total', 'statut', 'created_at']),
        ]);
    }
}
