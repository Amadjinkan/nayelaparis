<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    use HasFactory;

    protected $table = 'paiements';

    protected $fillable = [
        'commande_id', 'user_id',
        'stripe_payment_intent_id', 'stripe_charge_id', 'stripe_customer_id',
        'montant', 'devise',
        'marque_carte', 'quatre_derniers',
        'statut',
        'message_erreur', 'metadata',
        'paye_le',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'metadata' => 'array',
            'paye_le' => 'datetime',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
