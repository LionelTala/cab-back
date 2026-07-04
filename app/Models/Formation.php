<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formation extends Model
{
    protected $fillable = [
        'code',
        'title',
        'slug',
        'description',
        'duree_formation',
        'prix',
        'frais_scolarite',
        'is_active'
    ];

    // ⭐ Optionnel : casts pour automatiser les conversions
    protected $casts = [
        'is_active' => 'boolean',
        'duree_formation' => 'integer',
        'prix_formation' => 'decimal:2',
        'frais_scolarite' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function waves(): HasMany
    {
        return $this->hasMany(Wave::class);
    }
}
