<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Retour extends Model
{
    use HasFactory;

    protected $table = 'retours';

    protected $fillable = [
        'numero_rma', 'commande_id', 'user_id',
        'motif', 'description', 'statut',
        'montant_rembourse', 'stripe_refund_id',
        'note_client', 'note_admin', 'motif_refus',
        'etiquette_retour',
        'approuve_le', 'recu_le', 'rembourse_le',
    ];

    protected function casts(): array
    {
        return [
            'montant_rembourse' => 'decimal:2',
            'approuve_le' => 'datetime',
            'recu_le' => 'datetime',
            'rembourse_le' => 'datetime',
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

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRetour::class);
    }

    /**
     * Génère un numéro RMA unique : RMA-YYYY-NNNNN
     */
    public static function genererNumeroRma(): string
    {
        $annee = date('Y');
        $dernier = static::where('numero_rma', 'like', "RMA-{$annee}-%")
            ->orderByDesc('id')
            ->first();

        $prochain = 1;
        if ($dernier) {
            $parts = explode('-', $dernier->numero_rma);
            $prochain = (int) end($parts) + 1;
        }

        return sprintf('RMA-%s-%05d', $annee, $prochain);
    }
}
