<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaiementController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    /**
     * POST /api/paiements/intent
     * Crée (ou recrée) un PaymentIntent pour une commande déjà existante.
     */
    public function creerIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commande_id' => 'required|integer|exists:commandes,id',
        ]);

        $commande = Commande::where('id', $data['commande_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($commande->estPayee()) {
            return response()->json([
                'message' => 'Cette commande est déjà payée.',
            ], 422);
        }

        try {
            $intent = $this->stripe->creerPaymentIntent($commande);
            return response()->json([
                'message' => 'PaymentIntent créé',
                'client_secret' => $intent['client_secret'],
                'payment_intent_id' => $intent['payment_intent_id'],
                'paiement_id' => $intent['paiement_id'] ?? null,
                'publishable_key' => $intent['publishable_key'],
                'commande' => [
                    'id' => $commande->id,
                    'numero' => $commande->numero,
                    'total' => $commande->total,
                    'devise' => $commande->devise,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Erreur PaymentIntent', ['msg' => $e->getMessage()]);
            return response()->json([
                'message' => 'Impossible d\'initialiser le paiement : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/paiements/confirmer
     * Appelé par le frontend après confirmation côté Stripe.js
     * (le webhook est la source de vérité, mais ceci accélère le retour UI)
     */
    public function confirmer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $paiement = $this->stripe->confirmerPaiement($data['payment_intent_id']);

        if (!$paiement) {
            return response()->json(['message' => 'Paiement introuvable'], 404);
        }

        return response()->json([
            'message' => 'Paiement vérifié',
            'statut' => $paiement->statut,
            'commande' => $paiement->commande->only(['id', 'numero', 'statut', 'total']),
        ]);
    }

    /**
     * POST /api/stripe/webhook
     * Endpoint appelé par Stripe pour notifier des événements.
     * NE PAS être protégé par auth — Stripe vérifie la signature.
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripe->verifierWebhook($payload, $signature);
        } catch (\Throwable $e) {
            Log::error('Webhook Stripe : signature invalide', ['msg' => $e->getMessage()]);
            return response('Invalid signature', 400);
        }

        Log::info('Webhook Stripe reçu', ['type' => $event->type]);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $pi = $event->data->object;
                $this->stripe->confirmerPaiement($pi->id);
                break;

            case 'payment_intent.payment_failed':
                $pi = $event->data->object;
                $paiement = Paiement::where('stripe_payment_intent_id', $pi->id)->first();
                if ($paiement) {
                    $paiement->update([
                        'statut' => 'failed',
                        'message_erreur' => $pi->last_payment_error->message ?? 'Paiement échoué',
                    ]);
                    if ($paiement->commande && $paiement->commande->statut === Commande::STATUT_PENDING) {
                        $paiement->commande->changerStatut(
                            Commande::STATUT_PENDING,
                            'Paiement Stripe échoué : ' . ($pi->last_payment_error->message ?? 'Paiement échoué')
                        );
                    }
                }
                break;

            case 'charge.refunded':
                $charge = $event->data->object;
                $paiement = Paiement::where('stripe_charge_id', $charge->id)->first();
                if ($paiement) {
                    $isFullRefund = (int) ($charge->amount_refunded ?? 0) >= (int) ($charge->amount ?? 0);
                    $paiement->update(['statut' => $isFullRefund ? 'refunded' : 'partial_refund']);
                    if ($isFullRefund && $paiement->commande && $paiement->commande->statut !== Commande::STATUT_REFUNDED) {
                        $paiement->commande?->changerStatut(
                            Commande::STATUT_REFUNDED,
                            'Remboursement confirmé automatiquement par Stripe.'
                        );
                    }
                }
                Log::info('Remboursement Stripe traité', ['charge' => $charge->id]);
                break;
        }

        return response('OK', 200);
    }
}
