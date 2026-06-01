<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Retour;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class StripeService
{
    public function __construct()
    {
        $secret = config('services.stripe.secret');
        if ($secret) {
            Stripe::setApiKey($secret);
        }
        Stripe::setApiVersion('2024-06-20');
    }

    /**
     * Crée un PaymentIntent Stripe pour une commande.
     * Retourne le client_secret à utiliser côté frontend (Stripe.js).
     *
     * @throws ApiErrorException
     */
    public function creerPaymentIntent(Commande $commande): array
    {
        $this->assertConfigured();

        // Stripe attend les montants en CENTIMES
        $montantCents = (int) round($commande->total * 100);
        $devise = strtolower(config('services.stripe.currency', 'cad'));

        $paymentIntent = PaymentIntent::create([
            'amount' => $montantCents,
            'currency' => $devise,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'commande_id' => $commande->id,
                'numero_commande' => $commande->numero,
                'user_id' => $commande->user_id,
            ],
            'description' => "Commande {$commande->numero} - NayeLa Paris",
            'receipt_email' => $commande->user->email,
        ]);

        // Enregistrer le paiement en BDD avec statut "pending"
        $paiement = Paiement::updateOrCreate(
            ['commande_id' => $commande->id],
            [
                'user_id' => $commande->user_id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'montant' => $commande->total,
                'devise' => strtoupper($devise),
                'statut' => 'pending',
            ]
        );

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'paiement_id' => $paiement->id,
            'publishable_key' => config('services.stripe.key'),
        ];
    }

    private function assertConfigured(): void
    {
        $publicKey = (string) config('services.stripe.key', '');
        $secretKey = (string) config('services.stripe.secret', '');

        if (!preg_match('/^pk_(test|live)_[A-Za-z0-9]{16,}$/', $publicKey)) {
            throw new \RuntimeException('STRIPE_KEY est absent ou invalide dans le fichier .env.');
        }

        if (!preg_match('/^sk_(test|live)_[A-Za-z0-9]{16,}$/', $secretKey)) {
            throw new \RuntimeException('STRIPE_SECRET est absent ou invalide dans le fichier .env.');
        }
    }

    /**
     * Confirme un paiement après notification Stripe (webhook).
     */
    public function confirmerPaiement(string $paymentIntentId): ?Paiement
    {
        $paiement = Paiement::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if (!$paiement) {
            Log::warning("Paiement Stripe non trouvé en BDD: {$paymentIntentId}");
            return null;
        }

        try {
            $wasSucceeded = $paiement->statut === 'succeeded';
            $pi = PaymentIntent::retrieve($paymentIntentId);

            $paiement->statut = match ($pi->status) {
                'succeeded' => 'succeeded',
                'processing' => 'processing',
                'requires_payment_method', 'canceled' => 'failed',
                default => 'pending',
            };

            if ($pi->status === 'succeeded') {
                $paiement->paye_le = now();

                // Récupérer la charge associée (carte utilisée)
                if (!empty($pi->latest_charge)) {
                    $paiement->stripe_charge_id = $pi->latest_charge;
                    $charge = \Stripe\Charge::retrieve($pi->latest_charge);
                    if ($charge->payment_method_details?->card) {
                        $card = $charge->payment_method_details->card;
                        $paiement->marque_carte = $card->brand;
                        $paiement->quatre_derniers = $card->last4;
                    }
                }

                if ($paiement->commande && $paiement->commande->statut !== Commande::STATUT_PAID) {
                    $paiement->commande->changerStatut(
                        Commande::STATUT_PAID,
                        'Paiement confirmé automatiquement par Stripe.'
                    );
                }
            }

            $paiement->save();

            if ($pi->status === 'succeeded' && !$wasSucceeded) {
                $paiementPourEmail = $paiement->fresh(['commande.user', 'commande.lignes']);
                app(EmailNotificationService::class)->paymentConfirmation($paiementPourEmail);
                if ($paiementPourEmail?->commande) {
                    app(MarketingService::class)->crediterFidelite($paiementPourEmail->commande);
                }
            }

            return $paiement;
        } catch (ApiErrorException $e) {
            Log::error("Erreur Stripe confirmation paiement", [
                'pi_id' => $paymentIntentId,
                'message' => $e->getMessage(),
            ]);
            $paiement->update([
                'statut' => 'failed',
                'message_erreur' => $e->getMessage(),
            ]);
            if ($paiement->commande && $paiement->commande->statut === Commande::STATUT_PENDING) {
                $paiement->commande->changerStatut(
                    Commande::STATUT_PENDING,
                    'Paiement refusé ou impossible à confirmer : ' . $e->getMessage()
                );
            }
            return $paiement;
        }
    }

    /**
     * Rembourse un paiement (total ou partiel) pour une demande de retour.
     */
    public function rembourser(Retour $retour, float $montant): ?Refund
    {
        $paiement = $retour->commande->paiement;

        if (!$paiement || $paiement->statut !== 'succeeded') {
            throw new \RuntimeException("Aucun paiement réussi à rembourser pour cette commande.");
        }

        if (!$paiement->stripe_payment_intent_id) {
            throw new \RuntimeException("Identifiant Stripe manquant.");
        }

        $montantCents = (int) round($montant * 100);

        try {
            $refund = Refund::create([
                'payment_intent' => $paiement->stripe_payment_intent_id,
                'amount' => $montantCents,
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'rma' => $retour->numero_rma,
                    'commande_id' => $retour->commande_id,
                ],
            ]);

            // Mettre à jour le retour
            $retour->update([
                'stripe_refund_id' => $refund->id,
                'montant_rembourse' => $montant,
                'statut' => 'rembourse',
                'rembourse_le' => now(),
            ]);

            // Mettre à jour le paiement
            $totalPaye = (float) $paiement->montant;
            $paiement->update([
                'statut' => $montant >= $totalPaye ? 'refunded' : 'partial_refund',
            ]);

            if ($montant >= $totalPaye) {
                if ($retour->commande->statut !== Commande::STATUT_REFUNDED) {
                    $retour->commande->changerStatut(
                        Commande::STATUT_REFUNDED,
                        'Commande remboursée après retour.'
                    );
                }
            }

            return $refund;
        } catch (ApiErrorException $e) {
            Log::error("Erreur Stripe remboursement", [
                'rma' => $retour->numero_rma,
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Échec du remboursement Stripe : " . $e->getMessage());
        }
    }

    /**
     * Vérifie la signature d'un webhook Stripe.
     */
    public function verifierWebhook(string $payload, string $signature): \Stripe\Event
    {
        $secret = config('services.stripe.webhook_secret');
        if (!$secret) {
            throw new \RuntimeException("Webhook secret Stripe non configuré.");
        }

        return \Stripe\Webhook::constructEvent($payload, $signature, $secret);
    }
}
