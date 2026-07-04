<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Etudiant extends Model
{
    protected $fillable = [
        'user_id',
        'formation_id',
        'matricule',
        'nom',
        'prenom',
        'email',
        'telephone',
        'niveau',
        'statut',
    ];

    protected $casts = [
        'statut' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }
}