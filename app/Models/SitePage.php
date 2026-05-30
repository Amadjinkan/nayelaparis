<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    use HasFactory;

    protected $table = 'site_pages';

    protected $fillable = [
        'slug',
        'title_fr',
        'title_en',
        'subtitle_fr',
        'subtitle_en',
        'content_fr',
        'content_en',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }
}
