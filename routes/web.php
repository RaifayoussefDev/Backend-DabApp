<?php

use App\Http\Controllers\ImportMotorcycleController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\PaymentController;
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

// Route::prefix('motorcycles')->group(function () {
//     Route::get('/', [MotorcycleController::class, 'index'])->name('motorcycles.index');
//     Route::get('/import', [MotorcycleController::class, 'showImportForm'])->name('motorcycles.import.form');
//     Route::post('/import', [MotorcycleController::class, 'import'])->name('motorcycles.import');
//     Route::get('/{id}', [MotorcycleController::class, 'show'])->name('motorcycles.show');
// });
Route::view('/test-google-login', 'Auth.google-test');
Route::view('/demo-support', 'demo-support')->name('demo-support');
Route::post('/demo-support/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (\Illuminate\Support\Facades\Auth::attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->route('demo-support')->with('success', 'You are now logged in! Check the Tawk.to widget.');
    }

    // Backdoor for demo if actual password unknown (OPTIONAL, removed for security unless asked)
    // For now standard auth.
    
    return back()->with('error', 'The provided credentials do not match our records.');
})->name('demo-support.login');

Route::post('/demo-support/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('demo-support');
})->name('demo-support.logout');
    Route::get('/paytabs/pay', [PaymentController::class, 'createPayment'])->name('paytabs.pay');
    Route::get('/paytabs/success', [PaymentController::class, 'paymentSuccess'])->name('paytabs.success');
    Route::get('/paytabs/failure', [PaymentController::class, 'paymentFailure'])->name('paytabs.failure');



// Routes pour l'import de motos (interface simple)
Route::get('/motorcycles/import', [ImportMotorcycleController::class, 'index'])->name('motorcycles.import');
Route::post('/motorcycles/import', [ImportMotorcycleController::class, 'import'])->name('motorcycles.import.process');
Route::get('/motorcycles/import/template', [ImportMotorcycleController::class, 'downloadTemplate'])->name('motorcycles.import.template');

Route::get('/routes/{id}', function ($id) {
    $route = \App\Models\Route::with(['waypoints', 'creator'])->findOrFail($id);
    return view('routes.show', compact('route'));
});
