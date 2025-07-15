<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Authentication;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Tymon\JWTAuth\Facades\JWTAuth;

class FirebasePhoneAuthController extends Controller
{
    /**
     * Vérifier si un utilisateur existe déjà
     */
    public function checkUserExists(Request $request)
    {
        $request->validate([
            'idToken' => 'required|string',
        ]);

        $auth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();

        try {
            $verifiedIdToken = $auth->verifyIdToken($request->idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $auth->getUser($firebaseUid);

            // Vérifier si l'utilisateur existe par téléphone ou email
            $user = null;

            if ($firebaseUser->phoneNumber) {
                $user = User::where('phone', $firebaseUser->phoneNumber)->first();
            }

            if (!$user && $firebaseUser->email) {
                $user = User::where('email', $firebaseUser->email)->first();
            }

            return response()->json([
                'userExists' => $user !== null,
                'user' => $user ? $user->only(['id', 'first_name', 'last_name', 'email', 'phone']) : null
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Token Firebase invalide : ' . $e->getMessage()
            ], 401);
        }
    }

    /**
     * Authentification par téléphone avec Firebase
     */

     public function loginWithFirebasePhone(Request $request)
     {
         $request->validate([
             'idToken' => 'required|string',
             'firstName' => 'required|string|max:255',
             'lastName' => 'required|string|max:255',
             'phoneNumber' => 'required|string'
         ]);

         $auth = (new Factory)
             ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
             ->createAuth();

         try {
             // Vérifier le token Firebase
             $verifiedIdToken = $auth->verifyIdToken($request->idToken);
             $firebaseUid = $verifiedIdToken->claims()->get('sub');
             $firebaseUser = $auth->getUser($firebaseUid);

             $phoneNumber = $request->phoneNumber;
             $firstName = $request->firstName;
             $lastName = $request->lastName;

             // Vérifier si l'utilisateur existe déjà
             $user = User::where('phone', $phoneNumber)->first();

             if (!$user) {
                 // Créer un nouvel utilisateur
                 $user = User::create([
                     'first_name' => $firstName,
                     'last_name' => $lastName,
                     'phone' => $phoneNumber,
                     'email' => $firebaseUser->email ?? null, // Email peut être null pour auth par téléphone
                     'password' => bcrypt(uniqid()), // Mot de passe aléatoire
                     'is_active' => true,
                     'verified' => true, // Vérifié par SMS
                     'role_id' => 1, // Rôle utilisateur par défaut
                     'is_online' => true,
                     'last_login' => now(),
                     'language' => 'fr',
                     'timezone' => 'Africa/Casablanca',
                     'two_factor_enabled' => false
                 ]);
             } else {
                 // Mettre à jour les informations de connexion
                 $user->update([
                     'first_name' => $firstName,
                     'last_name' => $lastName,
                     'is_online' => true,
                     'last_login' => now(),
                 ]);
             }

             // Générer le token JWT Laravel
             $token = JWTAuth::fromUser($user);
             $tokenExpiration = now()->addMonth();

             // Traçage de la connexion
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
                 'message' => 'Authentification réussie',
                 'user' => $user,
                 'token' => $token,
                 'token_expiration' => $tokenExpiration
             ]);

         } catch (\Throwable $e) {
             return response()->json([
                 'error' => 'Erreur lors de l\'authentification : ' . $e->getMessage()
             ], 401);
         }
     }
    /**
     * Connexion utilisateur existant (téléphone ou Google)
     */
    public function loginExistingUser(Request $request)
    {
        $request->validate([
            'idToken' => 'required|string',
        ]);

        $auth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();

        try {
            $verifiedIdToken = $auth->verifyIdToken($request->idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $auth->getUser($firebaseUid);

            // Chercher l'utilisateur par téléphone ou email
            $user = null;

            if ($firebaseUser->phoneNumber) {
                $user = User::where('phone', $firebaseUser->phoneNumber)->first();
            }

            if (!$user && $firebaseUser->email) {
                $user = User::where('email', $firebaseUser->email)->first();
            }

            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non trouvé'
                ], 404);
            }

            // Mettre à jour les informations de connexion
            $user->update([
                'is_online' => true,
                'last_login' => now(),
            ]);

            // Générer le token JWT Laravel
            $token = JWTAuth::fromUser($user);
            $tokenExpiration = now()->addMonth();

            // Traçage de la connexion
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
                'message' => 'Connexion réussie',
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
