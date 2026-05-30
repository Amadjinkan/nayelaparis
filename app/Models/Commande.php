<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commande extends Model
{
    use HasFactory;

    protected $table = 'commandes';

    protected $fillable = [
        'user_id', 'numero',
        'sous_total', 'frais_livraison', 'taxes', 'total', 'devise',
        'statut',
        'livr_destinataire', 'livr_ligne1', 'livr_ligne2',
        'livr_ville', 'livr_province', 'livr_code_postal', 'livr_pays',
        'numero_suivi', 'transporteur',
        'date_expedition', 'date_livraison',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sous_total' => 'decimal:2',
            'frais_livraison' => 'decimal:2',
            'taxes' => 'decimal:2',
            'total' => 'decimal:2',
            'date_expedition' => 'datetime',
            'date_livraison' => 'datetime',
        ];
    }

    // ===== Relations =====
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneCommande::class);
    }

    public function paiement(): HasOne
    {
        return $this->hasOne(Paiement::class)->latestOfMany();
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function retours(): HasMany
    {
        return $this->hasMany(Retour::class);
    }

    // ===== Helpers =====

    /**
     * Génère un numéro de commande unique : NP-YYYY-NNNNNN
     */
    public static function genererNumero(): string
    {
        $annee = date('Y');
        $dernier = static::where('numero', 'like', "NP-{$annee}-%")
            ->orderByDesc('id')
            ->first();

        $prochain = 1;
        if ($dernier) {
            $parts = explode('-', $dernier->numero);
            $prochain = (int) end($parts) + 1;
        }

        return sprintf('NP-%s-%06d', $annee, $prochain);
    }

    public function estPayee(): bool
    {
        return in_array($this->statut, ['paid', 'processing', 'shipped', 'delivered']);
    }

    public function peutEtreRetournee(): bool
    {
        // Une commande livrée depuis moins de 30 jours peut être retournée
        if ($this->statut !== 'delivered') {
            return false;
        }
        if (!$this->date_livraison) {
            return false;
        }
        return $this->date_livraison->diffInDays(now()) <= 30;
    }
}
