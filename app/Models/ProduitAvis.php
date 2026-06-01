<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduitAvis extends Model
{
    protected $table = 'produit_avis';

    protected $fillable = ['user_id', 'produit_id', 'commande_id', 'note', 'commentaire', 'statut', 'approved_at'];

    protected function casts(): array
    {
        return [
            'note' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
