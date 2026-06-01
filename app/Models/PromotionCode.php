<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionCode extends Model
{
    protected $fillable = [
        'code', 'nom', 'type', 'value', 'min_amount',
        'usage_limit', 'used_count', 'per_user_limit',
        'starts_at', 'ends_at', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'actif' => 'boolean',
        ];
    }

    public function isUsable(float $subtotal, ?User $user = null): bool
    {
        if (!$this->actif || $subtotal < (float) $this->min_amount) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }
        if ($user && $this->per_user_limit) {
            $usedByUser = Commande::where('user_id', $user->id)
                ->where('promotion_code_id', $this->id)
                ->whereNotIn('statut', [Commande::STATUT_CANCELLED, Commande::STATUT_REFUNDED])
                ->count();

            if ($usedByUser >= $this->per_user_limit) {
                return false;
            }
        }

        return true;
    }

    public function discountFor(float $subtotal): float
    {
        return match ($this->type) {
            'percent' => min($subtotal, round($subtotal * ((float) $this->value / 100), 2)),
            'fixed' => min($subtotal, (float) $this->value),
            default => 0.0,
        };
    }
}
