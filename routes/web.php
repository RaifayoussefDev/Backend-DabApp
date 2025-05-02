<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test-mail', function () {
    $user = \App\Models\User::first(); // Replace with actual user
    $otp = 1234;

    try {
        $user->notify(new \App\Notifications\SendOtpNotification($otp));
        return 'Mail sent!';
    } catch (\Exception $e) {
        return 'Mail failed: ' . $e->getMessage();
    }
});
