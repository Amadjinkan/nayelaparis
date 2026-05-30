<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneRetour extends Model
{
    use HasFactory;

    protected $table = 'lignes_retours';

    protected $fillable = [
        'retour_id', 'ligne_commande_id',
        'quantite', 'montant',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'montant' => 'decimal:2',
        ];
    }

    public function retour(): BelongsTo
    {
        return $this->belongsTo(Retour::class);
    }

    public function ligneCommande(): BelongsTo
    {
        return $this->belongsTo(LigneCommande::class, 'ligne_commande_id');
    }
}
