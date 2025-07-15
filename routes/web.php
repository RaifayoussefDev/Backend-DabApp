<?php

use App\Http\Controllers\MotorcycleController;
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

Route::prefix('motorcycles')->group(function () {
    Route::get('/', [MotorcycleController::class, 'index'])->name('motorcycles.index');
    Route::get('/import', [MotorcycleController::class, 'showImportForm'])->name('motorcycles.import.form');
    Route::post('/import', [MotorcycleController::class, 'import'])->name('motorcycles.import');
    Route::get('/{id}', [MotorcycleController::class, 'show'])->name('motorcycles.show');
});
Route::view('/test-google-login', 'Auth.google-test');
