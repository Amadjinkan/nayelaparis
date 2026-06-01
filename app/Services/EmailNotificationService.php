<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    public function accountCreated(User $user): void
    {
        $this->send(
            $user->email,
            'Bienvenue chez NayeLa Paris',
            $this->layout(
                'Bienvenue ' . e($user->prenom) . ',',
                '<p>Votre compte NayeLa Paris a bien été créé.</p>
                <p>Vous pouvez maintenant suivre vos commandes, télécharger vos factures et gérer vos informations depuis votre espace client.</p>'
            ),
            ['type' => 'account_created', 'user_id' => $user->id]
        );
    }

    public function passwordReset(User $user, string $temporaryPassword): void
    {
        $this->send(
            $user->email,
            'Réinitialisation de votre mot de passe',
            $this->layout(
                'Réinitialisation du mot de passe',
                '<p>Une demande de réinitialisation a été effectuée pour votre compte NayeLa Paris.</p>
                <p>Votre mot de passe temporaire est :</p>
                <p style="font-size:22px;letter-spacing:.08em"><strong>' . e($temporaryPassword) . '</strong></p>
                <p>Connectez-vous avec ce mot de passe temporaire, puis modifiez-le depuis votre espace client.</p>
                <p>Si vous n’êtes pas à l’origine de cette demande, contactez-nous rapidement.</p>'
            ),
            ['type' => 'password_reset', 'user_id' => $user->id]
        );
    }

    public function newOrderForAdmin(Commande $commande): void
    {
        $this->send(
            $this->contactEmail(),
            'Nouvelle commande ' . $commande->numero,
            $this->layout(
                'Nouvelle commande reçue',
                $this->orderSummary($commande)
                . '<p>Cette commande est visible dans le panneau d’administration.</p>'
            ),
            ['type' => 'new_order_admin', 'commande_id' => $commande->id]
        );
    }

    public function orderConfirmation(Commande $commande): void
    {
        $this->send(
            $commande->user->email,
            'Confirmation de commande ' . $commande->numero,
            $this->layout(
                'Commande enregistrée',
                '<p>Bonjour ' . e($commande->user->prenom) . ',</p>'
                . '<p>Votre commande <strong>' . e($commande->numero) . '</strong> a bien été enregistrée.</p>'
                . $this->orderSummary($commande)
            ),
            ['type' => 'order_confirmation', 'commande_id' => $commande->id]
        );
    }

    public function paymentConfirmation(Paiement $paiement): void
    {
        $commande = $paiement->commande;
        if (!$commande || !$commande->user) {
            return;
        }

        $this->send(
            $commande->user->email,
            'Paiement confirmé pour ' . $commande->numero,
            $this->layout(
                'Paiement confirmé',
                '<p>Bonjour ' . e($commande->user->prenom) . ',</p>
                <p>Nous confirmons la réception du paiement pour votre commande <strong>' . e($commande->numero) . '</strong>.</p>'
                . $this->orderSummary($commande)
            ),
            ['type' => 'payment_confirmation', 'commande_id' => $commande->id, 'paiement_id' => $paiement->id]
        );
    }

    public function shipmentConfirmation(Commande $commande): void
    {
        $tracking = $commande->numero_suivi
            ? '<p>Numéro de suivi : <strong>' . e($commande->numero_suivi) . '</strong></p>'
            : '';

        $this->send(
            $commande->user->email,
            'Votre commande ' . $commande->numero . ' est expédiée',
            $this->layout(
                'Commande expédiée',
                '<p>Bonjour ' . e($commande->user->prenom) . ',</p>
                <p>Votre commande <strong>' . e($commande->numero) . '</strong> vient d’être expédiée.</p>'
                . ($commande->transporteur ? '<p>Transporteur : <strong>' . e($commande->transporteur) . '</strong></p>' : '')
                . $tracking
            ),
            ['type' => 'shipment_confirmation', 'commande_id' => $commande->id]
        );
    }

    public function deliveryConfirmation(Commande $commande): void
    {
        $this->send(
            $commande->user->email,
            'Votre commande ' . $commande->numero . ' est livrée',
            $this->layout(
                'Commande livrée',
                '<p>Bonjour ' . e($commande->user->prenom) . ',</p>
                <p>Votre commande <strong>' . e($commande->numero) . '</strong> est indiquée comme livrée.</p>
                <p>Merci pour votre confiance.</p>'
            ),
            ['type' => 'delivery_confirmation', 'commande_id' => $commande->id]
        );
    }

    public function contactMessage(array $data): bool
    {
        $nom = trim((string) ($data['nom'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $telephone = trim((string) ($data['telephone'] ?? ''));
        $sujet = trim((string) ($data['sujet'] ?? 'Demande depuis le site'));
        $message = trim((string) ($data['message'] ?? ''));

        return $this->send(
            $this->contactEmail(),
            'Nouveau message client - ' . $sujet,
            $this->layout(
                'Nouveau message client',
                '<p><strong>Nom :</strong> ' . e($nom) . '</p>'
                . '<p><strong>Email :</strong> ' . e($email) . '</p>'
                . ($telephone ? '<p><strong>Téléphone :</strong> ' . e($telephone) . '</p>' : '')
                . '<p><strong>Sujet :</strong> ' . e($sujet) . '</p>'
                . '<div style="margin-top:18px;padding:18px;border:1px solid #e5dccd;background:#faf8f4;white-space:pre-line">'
                . e($message)
                . '</div>'
            ),
            ['type' => 'contact_message', 'from' => $email],
            $email
        );
    }

    private function send(string $to, string $subject, string $html, array $context = [], ?string $replyTo = null): bool
    {
        try {
            Mail::html($html, function ($message) use ($to, $subject, $replyTo) {
                $message->to($to)->subject($subject);

                if ($replyTo) {
                    $message->replyTo($replyTo);
                }
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Échec envoi email NayeLa Paris', [
                ...$context,
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function contactEmail(): string
    {
        try {
            $email = SiteSetting::where('key', 'contact_email')->value('value');
            if ($email) {
                return $email;
            }
        } catch (\Throwable) {
        }

        return config('mail.from.address') ?: 'contact@nayelaparis.com';
    }

    private function orderSummary(Commande $commande): string
    {
        $commande->loadMissing(['lignes', 'user']);

        $rows = $commande->lignes->map(function ($ligne) use ($commande) {
            return '<tr>
                <td style="padding:10px 0;border-bottom:1px solid #eee">' . e($ligne->nom_produit) . '<br><span style="color:#777;font-size:12px">Taille : ' . e($ligne->taille ?: 'Unique') . '</span></td>
                <td style="padding:10px 0;border-bottom:1px solid #eee;text-align:center">' . (int) $ligne->quantite . '</td>
                <td style="padding:10px 0;border-bottom:1px solid #eee;text-align:right">' . $this->money($ligne->sous_total, $commande->devise) . '</td>
            </tr>';
        })->implode('');

        return '<div style="margin:22px 0;padding:18px;border:1px solid #e5dccd;background:#faf8f4">
            <p><strong>Commande :</strong> ' . e($commande->numero) . '</p>
            <p><strong>Total :</strong> ' . $this->money($commande->total, $commande->devise) . '</p>
            <table style="width:100%;border-collapse:collapse;margin-top:14px">
                <thead><tr><th style="text-align:left">Article</th><th>Qté</th><th style="text-align:right">Total</th></tr></thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </div>';
    }

    private function layout(string $title, string $content): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"></head>
        <body style="margin:0;background:#faf8f4;color:#1a1a18;font-family:Arial,sans-serif">
          <div style="max-width:680px;margin:0 auto;padding:34px 20px">
            <div style="background:#fff;border:1px solid #e5dccd;padding:34px">
              <div style="letter-spacing:.16em;text-transform:uppercase;color:#a98238;font-size:12px;margin-bottom:16px">NayeLa Paris</div>
              <h1 style="font-family:Georgia,serif;font-weight:400;font-size:30px;margin:0 0 18px">' . $title . '</h1>
              <div style="font-size:15px;line-height:1.8">' . $content . '</div>
              <p style="margin-top:30px;color:#777;font-size:12px">Cet email a été envoyé automatiquement par NayeLa Paris.</p>
            </div>
          </div>
        </body></html>';
    }

    private function money(float|string $value, ?string $devise = 'CAD'): string
    {
        return number_format((float) $value, 2, '.', ' ') . ' ' . e($devise ?: 'CAD');
    }
}
