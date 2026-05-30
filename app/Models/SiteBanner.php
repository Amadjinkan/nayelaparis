<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteBanner extends Model
{
    use HasFactory;

    protected $table = 'site_banners';

    protected $fillable = [
        'key',
        'eyebrow_fr',
        'eyebrow_en',
        'title_fr',
        'title_en',
        'subtitle_fr',
        'subtitle_en',
        'primary_label_fr',
        'primary_label_en',
        'primary_page',
        'secondary_label_fr',
        'secondary_label_en',
        'secondary_page',
        'image',
        'position',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
