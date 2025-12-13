<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Illuminate\Database\Eloquent\Relations\BelongsTo;

   class EventInterest extends Model
   {
       protected $fillable = [
           'event_id',
           'user_id',
       ];

       public $timestamps = false;

       protected $dates = ['created_at'];

       public function event(): BelongsTo
       {
           return $this->belongsTo(Event::class);
       }

       public function user(): BelongsTo
       {
           return $this->belongsTo(User::class);
       }
   }
