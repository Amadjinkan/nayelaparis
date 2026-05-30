<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommandeController extends Controller
{
    /**
     * GET /api/admin/commandes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Commande::with(['user:id,prenom,nom,email', 'lignes', 'paiement']);

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

        $data = $request->validate([
            'statut' => 'sometimes|in:pending,paid,processing,shipped,delivered,cancelled',
            'numero_suivi' => 'sometimes|nullable|string|max:100',
            'transporteur' => 'sometimes|nullable|string|max:50',
            'date_expedition' => 'sometimes|nullable|date',
            'date_livraison' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ]);

        // Si on marque comme expédiée, mettre la date par défaut
        if (isset($data['statut']) && $data['statut'] === 'shipped' && empty($data['date_expedition'])) {
            $data['date_expedition'] = now();
        }
        if (isset($data['statut']) && $data['statut'] === 'delivered' && empty($data['date_livraison'])) {
            $data['date_livraison'] = now();
        }

        $commande->update($data);

        return response()->json([
            'message' => 'Commande mise à jour',
            'commande' => $commande->fresh(['lignes', 'user']),
        ]);
    }

    /**
     * GET /api/admin/statistiques
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_commandes' => Commande::count(),
            'en_attente' => Commande::where('statut', 'pending')->count(),
            'payees' => Commande::whereIn('statut', ['paid', 'processing', 'shipped', 'delivered'])->count(),
            'chiffre_affaire' => Commande::whereIn('statut', ['paid', 'processing', 'shipped', 'delivered'])
                ->sum('total'),
            'commandes_recentes' => Commande::with('user:id,prenom,nom')
                ->latest()
                ->take(5)
                ->get(['id', 'numero', 'user_id', 'total', 'statut', 'created_at']),
        ]);
    }
}
