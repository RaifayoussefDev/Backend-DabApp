<?php

// app/Models/BankCard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankCard extends Model
{
    protected $fillable = [
        'user_id',
        'card_number',
        'card_holder_name',
        'expiration_date',
        'cvv',
        'card_type_id',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cardType()
    {
        return $this->belongsTo(CardType::class);
    }
}
