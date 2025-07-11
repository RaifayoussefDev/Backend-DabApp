<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Authentication extends Model
{
    use HasFactory;

    // Nom de la table
    protected $table = 'authentication';

    // Attributs autorisés à être remplis en masse
    protected $fillable = [
        'user_id',
        'token',
        'token_expiration',
        'refresh_token',
        'refresh_token_expiration',
        'is_online',
        'connection_date',
    ];

    // Casting des types de données
    protected $casts = [
        'token_expiration' => 'datetime',
        'refresh_token_expiration' => 'datetime',
        'connection_date' => 'datetime',
        'is_online' => 'boolean',
    ];

    // Relation avec l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
