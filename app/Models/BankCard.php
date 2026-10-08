<?php

// app/Models/BankCard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankCard extends Model
{
    protected $fillable = [
        'user_id',
        'payment_token',
        'last_four',
        'brand',
        'expiry_month',
        'expiry_year',
        'card_holder_name',
        'card_type_id',
        'is_default'
    ];

    // Never serialize the PayTabs token (it can charge the card) nor any legacy raw card
    // column that may still exist on older databases.
    protected $hidden = [
        'payment_token',
        'card_number',
        'cvv',
        'expiration_date',
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
