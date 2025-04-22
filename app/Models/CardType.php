<?php

// app/Models/CardType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardType extends Model
{
    protected $fillable = ['name'];

    public function bankCards()
    {
        return $this->hasMany(BankCard::class);
    }
}

