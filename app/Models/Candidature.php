<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidature extends Model
{
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'formation',
        'formation_id',
        'niveau',
        'message',
        'statut',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formationRelation(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'formation_id');
    }
}