<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'prenom',
        'nom',
        'email',
        'mot_de_passe',
        'telephone',
        'role',
        'newsletter',
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mot_de_passe' => 'hashed',
            'newsletter' => 'boolean',
        ];
    }

    /**
     * Laravel utilise par défaut la colonne 'password'.
     * On lui dit d'utiliser 'mot_de_passe' à la place.
     */
    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // ===== Relations =====

    public function adresses(): HasMany
    {
        return $this->hasMany(Adresse::class);
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
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

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
