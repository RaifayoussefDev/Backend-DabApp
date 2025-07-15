<?php

namespace App\Http\Controllers;

use App\Models\Authentication;
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

            $email = $firebaseUser->email ?? null;
            $phone = $firebaseUser->phoneNumber ?? null;
            $name = $firebaseUser->displayName ?? 'Utilisateur';

            // 🔁 Générer un email fictif si absent (obligatoire pour unicité)
            if (!$email && $phone) {
                $email = str_replace(['+', ' '], '', $phone) . '@phone.firebase';
            }

            // 🧩 Découper le nom complet
            $nameParts = explode(' ', $name);
            $firstName = $nameParts[0] ?? 'Utilisateur';
            $lastName = $nameParts[1] ?? '';

            // 🎯 Recherche utilisateur : par email s’il existe, sinon par phone
            $user = User::where('email', $email)->orWhere('phone', $phone)->first();

            // 👤 Créer l'utilisateur s'il n'existe pas
            if (!$user) {
                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => bcrypt(uniqid()), // mot de passe fictif
                    'is_active' => true,
                    'verified' => true,
                    'role_id' => 1,
                    'is_online' => true,
                    'last_login' => now(),
                    'language' => 'fr',
                    'timezone' => 'Africa/Casablanca',
                    'two_factor_enabled' => false
                ]);
            } else {
                // 🔄 Mise à jour si déjà existant
                $user->is_online = true;
                $user->last_login = now();

                if (!$user->phone && $phone) {
                    $user->phone = $phone;
                }

                $user->save();
            }

            // 🔐 Générer le token JWT Laravel
            $token = JWTAuth::fromUser($user);
            $tokenExpiration = now()->addMonth();

            // 📌 Traçage de la connexion
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
}
