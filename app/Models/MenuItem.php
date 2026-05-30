<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $table = 'menu_items';

    protected $fillable = [
        'slug',
        'label_fr',
        'label_en',
        'type',
        'page_key',
        'url',
        'position',
        'is_active',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'label_fr' => $this->label_fr,
            'label_en' => $this->label_en ?: $this->label_fr,
            'type' => $this->type,
            'page' => $this->page_key,
            'page_key' => $this->page_key,
            'url' => $this->url,
            'order' => $this->position,
            'position' => $this->position,
            'active' => $this->is_active,
            'is_active' => $this->is_active,
            'locked' => $this->is_locked,
            'is_locked' => $this->is_locked,
        ];
    }
}
