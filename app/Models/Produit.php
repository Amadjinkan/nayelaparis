<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use HasFactory;

    protected $table = 'produits';

    protected $fillable = [
        'nom',
        'categorie',
        'prix',
        'stock',
        'tailles',
        'couleurs',
        'description',
        'emoji',
        'image',
        'featured',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
            'stock' => 'integer',
            'featured' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function lignesCommandes(): HasMany
    {
        return $this->hasMany(LigneCommande::class);
    }

    // ===== Scopes =====
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeEnStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
