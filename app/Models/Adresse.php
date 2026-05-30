<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adresse extends Model
{
    use HasFactory;

    protected $table = 'adresses';

    protected $fillable = [
        'user_id',
        'label',
        'ligne1',
        'ligne2',
        'ville',
        'province',
        'code_postal',
        'pays',
        'par_defaut',
    ];

    protected function casts(): array
    {
        return [
            'par_defaut' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
