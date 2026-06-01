<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDashboardController extends Controller
{
    private const PAID_STATUSES = [
        Commande::STATUT_PAID,
        Commande::STATUT_PROCESSING,
        Commande::STATUT_SHIPPED,
        Commande::STATUT_DELIVERED,
    ];

    public function index(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $orders = $this->ordersQuery($from, $to);
        $paidOrders = $this->ordersQuery($from, $to)->whereIn('statut', self::PAID_STATUSES);

        $paidCount = (clone $paidOrders)->count();
        $revenue = (float) (clone $paidOrders)->sum('total');
        $taxes = (float) (clone $paidOrders)->sum('taxes');
        $discounts = (float) (clone $paidOrders)->sum('remise');

        return response()->json([
            'periode' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
            'chiffre_affaires' => $revenue,
            'nombre_commandes' => (clone $orders)->count(),
            'commandes_payees' => $paidCount,
            'taxes_collectees' => $taxes,
            'remises_accordees' => $discounts,
            'panier_moyen' => $paidCount > 0 ? round($revenue / $paidCount, 2) : 0,
            'statuts' => $this->statusCounts($from, $to),
            'produits_plus_vendus' => $this->topProducts($from, $to),
            'clients_plus_actifs' => $this->topCustomers($from, $to),
            'ventes_par_mois' => $this->monthlyRevenue($from, $to),
            'commandes_recentes' => $this->recentOrders($from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $filename = 'nayela-dashboard-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($from, $to) {
            echo "\xEF\xBB\xBF";
            echo "sep=;\n";

            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Numero',
                'Date',
                'Client',
                'Email',
                'Statut',
                'Sous-total',
                'Remise',
                'Livraison',
                'Taxes',
                'Total',
                'Devise',
                'Code promo',
            ], ';');

            $this->ordersQuery($from, $to)
                ->with('user:id,prenom,nom,email')
                ->oldest()
                ->chunk(200, function ($commandes) use ($out) {
                    foreach ($commandes as $commande) {
                        fputcsv($out, [
                            $commande->numero,
                            $commande->created_at?->format('Y-m-d H:i:s'),
                            trim(($commande->user?->prenom ?? '') . ' ' . ($commande->user?->nom ?? '')),
                            $commande->user?->email,
                            Commande::labelStatut($commande->statut),
                            $this->csvMoney($commande->sous_total),
                            $this->csvMoney($commande->remise ?? 0),
                            $this->csvMoney($commande->frais_livraison),
                            $this->csvMoney($commande->taxes),
                            $this->csvMoney($commande->total),
                            $commande->devise,
                            $commande->code_promo,
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function dateRange(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : null;

        return [$from, $to];
    }

    private function ordersQuery($from = null, $to = null): Builder
    {
        return Commande::query()
            ->when($from, fn($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn($query) => $query->where('created_at', '<=', $to));
    }

    private function statusCounts($from = null, $to = null): array
    {
        return $this->ordersQuery($from, $to)
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->groupBy('statut')
            ->pluck('total', 'statut')
            ->toArray();
    }

    private function topProducts($from = null, $to = null)
    {
        return LigneCommande::query()
            ->join('commandes', 'commandes.id', '=', 'lignes_commandes.commande_id')
            ->whereIn('commandes.statut', self::PAID_STATUSES)
            ->when($from, fn($query) => $query->where('commandes.created_at', '>=', $from))
            ->when($to, fn($query) => $query->where('commandes.created_at', '<=', $to))
            ->select([
                'lignes_commandes.produit_id',
                'lignes_commandes.nom_produit',
                DB::raw('SUM(lignes_commandes.quantite) as quantite_vendue'),
                DB::raw('SUM(lignes_commandes.sous_total) as chiffre_affaires'),
            ])
            ->groupBy('lignes_commandes.produit_id', 'lignes_commandes.nom_produit')
            ->orderByDesc('quantite_vendue')
            ->limit(8)
            ->get();
    }

    private function topCustomers($from = null, $to = null)
    {
        return User::query()
            ->join('commandes', 'commandes.user_id', '=', 'users.id')
            ->whereIn('commandes.statut', self::PAID_STATUSES)
            ->when($from, fn($query) => $query->where('commandes.created_at', '>=', $from))
            ->when($to, fn($query) => $query->where('commandes.created_at', '<=', $to))
            ->select([
                'users.id',
                'users.prenom',
                'users.nom',
                'users.email',
                DB::raw('COUNT(commandes.id) as commandes'),
                DB::raw('SUM(commandes.total) as total_depense'),
                DB::raw('MAX(commandes.created_at) as derniere_commande'),
            ])
            ->groupBy('users.id', 'users.prenom', 'users.nom', 'users.email')
            ->orderByDesc('total_depense')
            ->limit(8)
            ->get();
    }

    private function monthlyRevenue($from = null, $to = null)
    {
        return $this->ordersQuery($from, $to)
            ->whereIn('statut', self::PAID_STATUSES)
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mois"),
                DB::raw('SUM(total) as chiffre_affaires'),
                DB::raw('COUNT(*) as commandes'),
            ])
            ->groupBy('mois')
            ->orderBy('mois')
            ->limit(12)
            ->get();
    }

    private function recentOrders($from = null, $to = null)
    {
        return $this->ordersQuery($from, $to)
            ->with('user:id,prenom,nom,email')
            ->latest()
            ->limit(8)
            ->get(['id', 'numero', 'user_id', 'statut', 'total', 'taxes', 'created_at']);
    }

    private function csvMoney($value): string
    {
        return number_format((float) $value, 2, ',', '');
    }
}
