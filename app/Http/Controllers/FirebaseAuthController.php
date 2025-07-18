<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class FirebaseAuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct()
    {
        $this->firebaseAuth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();
    }

    public function loginWithFirebase(Request $request)
    {
        $request->validate([
            'idToken' => 'required|string',
        ]);
    
        $auth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();
    
        try {
            // ✅ Vérifie le token Firebase
            $verifiedIdToken = $auth->verifyIdToken($request->idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $auth->getUser($firebaseUid);
    
            $email = $firebaseUser->email;
            $name = $firebaseUser->displayName ?? 'Utilisateur Google';
    
            // 🎯 Vérifie si utilisateur Laravel existe déjà
            $user = User::where('email', $email)->first();
    
            // 👤 Si utilisateur inexistant → le créer
            if (!$user) {
                $user = User::create([
                    'first_name' => explode(' ', $name)[0],
                    'last_name' => explode(' ', $name)[1] ?? '',
                    'email' => $email,
                    'password' => bcrypt(uniqid()),    // mot de passe aléatoire (non utilisé)
                    'is_active' => true,
                    'verified' => true,
                    'role_id' => 1, // Rôle utilisateur par défaut
                    'is_online' => true,
                    'last_login' => now(),
                    'language' => 'fr',
                    'timezone' => 'Africa/Casablanca',
                    'two_factor_enabled' => false
                ]);
            } else {
                // 🔄 Sinon, mettre à jour login info
                $user->is_online = true;
                $user->last_login = now();
                $user->save();
            }
    
            // 🔐 Génère le token JWT Laravel
            $token = JWTAuth::fromUser($user);
            $tokenExpiration = now()->addMonth();
    
            // --- Traçage de la connexion ---
            Authentication::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'token' => $token,
                    'token_expiration' => $tokenExpiration,
                    'is_online' => true,
                    'connection_date' => now(),
                ]
            );
    
            return response()->json([
                'user' => $user,
                'token' => $token,
                'token_expiration' => $tokenExpiration
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Token Firebase invalide : ' . $e->getMessage()
            ], 401);
        }
}
