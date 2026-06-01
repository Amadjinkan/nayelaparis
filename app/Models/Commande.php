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

    public const STATUT_PENDING = 'pending';
    public const STATUT_PAID = 'paid';
    public const STATUT_PROCESSING = 'processing';
    public const STATUT_SHIPPED = 'shipped';
    public const STATUT_DELIVERED = 'delivered';
    public const STATUT_CANCELLED = 'cancelled';
    public const STATUT_REFUNDED = 'refunded';

    public const STATUTS = [
        self::STATUT_PENDING,
        self::STATUT_PAID,
        self::STATUT_PROCESSING,
        self::STATUT_SHIPPED,
        self::STATUT_DELIVERED,
        self::STATUT_CANCELLED,
        self::STATUT_REFUNDED,
    ];

    public const STATUT_LABELS = [
        self::STATUT_PENDING => 'En attente',
        self::STATUT_PAID => 'Payée',
        self::STATUT_PROCESSING => 'En préparation',
        self::STATUT_SHIPPED => 'Expédiée',
        self::STATUT_DELIVERED => 'Livrée',
        self::STATUT_CANCELLED => 'Annulée',
        self::STATUT_REFUNDED => 'Remboursée',
    ];

    protected $fillable = [
        'user_id', 'numero',
        'sous_total', 'frais_livraison', 'taxes', 'total', 'devise',
        'promotion_code_id', 'code_promo', 'remise', 'points_fidelite_gagnes',
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
            'remise' => 'decimal:2',
            'points_fidelite_gagnes' => 'integer',
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

    public function historiqueStatuts(): HasMany
    {
        return $this->hasMany(CommandeStatut::class)->oldest();
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

    public static function labelStatut(string $statut): string
    {
        return self::STATUT_LABELS[$statut] ?? $statut;
    }

    public function changerStatut(string $statut, ?string $note = null, ?int $userId = null): void
    {
        if (!in_array($statut, self::STATUTS, true)) {
            throw new \InvalidArgumentException("Statut de commande invalide : {$statut}");
        }

        $ancienStatut = $this->statut;
        if ($ancienStatut !== $statut) {
            $this->forceFill(['statut' => $statut])->save();
        }

        if ($ancienStatut !== $statut || $note) {
            $this->historiqueStatuts()->create([
                'statut' => $statut,
                'label' => self::labelStatut($statut),
                'note' => $note,
                'user_id' => $userId,
            ]);
        }
    }

    public function estPayee(): bool
    {
        return in_array($this->statut, [
            self::STATUT_PAID,
            self::STATUT_PROCESSING,
            self::STATUT_SHIPPED,
            self::STATUT_DELIVERED,
            self::STATUT_REFUNDED,
        ], true);
    }

    public function peutEtreRetournee(): bool
    {
        // Une commande livrée depuis moins de 30 jours peut être retournée
        if ($this->statut !== self::STATUT_DELIVERED) {
            return false;
        }
        if (!$this->date_livraison) {
            return false;
        }
        return $this->date_livraison->diffInDays(now()) <= 30;
    }
}
