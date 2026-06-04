<?php

use App\Models\ChatSession;
use App\Models\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Chat Private Channels
|--------------------------------------------------------------------------
*/

// Messages d'une session de chat — accessible au user et au provider de la session
Broadcast::channel('chat.{sessionId}', function ($user, $sessionId) {
    $session = ChatSession::find($sessionId);
    if (!$session) {
        return false;
    }

    $isUser     = $session->user_id === $user->id;
    $isProvider = optional(ServiceProvider::find($session->provider_id))->user_id === $user->id;

    return $isUser || $isProvider;
});

// Notifications de session pour un user spécifique
Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Notifications de session pour un provider (via son user_id)
Broadcast::channel('chat.provider.{providerId}', function ($user, $providerId) {
    return ServiceProvider::where('id', $providerId)
        ->where('user_id', $user->id)
        ->exists();
});
