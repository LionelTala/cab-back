<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wave extends Model
{
    protected $fillable = [
        'formation_id',
        'code_vague',      // ⭐ Nouveau
        'name',
        'start_date',
        'end_date',
         'status',
        'is_active'        // ⭐ Nouveau
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
         'is_active' => 'boolean',
    ];

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    // Accesseur pour le nombre d'étudiants inscrits (à implémenter plus tard)
    public function getInscritsCountAttribute()
    {
        // À compléter quand la table étudiants sera créée
        return 0;
    }
}
