<?php

namespace App\Http\Controllers;

use App\Models\Authentication;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class FirebaseAuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct()
    {
        $this->firebaseAuth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();
    }

    /**
     * Connexion avec numéro de téléphone et mot de passe
     */
    public function loginWithPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Données invalides',
                'details' => $validator->errors()
            ], 400);
        }

        try {
            $phone = $request->phone;
            $password = $request->password;

            // Rechercher l'utilisateur par numéro de téléphone
            $user = User::where('phone', $phone)->first();

            if (!$user) {
                return response()->json([
                    'error' => 'Aucun compte trouvé avec ce numéro de téléphone'
                ], 404);
            }

            // Vérifier le mot de passe
            if (!Hash::check($password, $user->password)) {
                return response()->json([
                    'error' => 'Mot de passe incorrect'
                ], 401);
            }

            // Vérifier si le compte est actif
            if (!$user->is_active) {
                return response()->json([
                    'error' => 'Compte désactivé. Contactez l\'administrateur'
                ], 403);
            }

            // Mise à jour du statut de connexion
            $user->is_online = true;
            $user->last_login = now();
            $user->save();

            // Générer le token JWT
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
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role_id' => $user->role_id,
                    'is_active' => $user->is_active,
                    'verified' => $user->verified,
                    'last_login' => $user->last_login,
                ],
                'token' => $token,
                'token_expiration' => $tokenExpiration
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Erreur lors de la connexion',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connexion avec Firebase (Google uniquement)
     */
    public function loginWithFirebase(Request $request)
    {
        $request->validate([
            'idToken' => 'required|string',
        ]);

        $auth = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
            ->createAuth();

        try {
            // Vérifier le token Firebase
            $verifiedIdToken = $auth->verifyIdToken($request->idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $auth->getUser($firebaseUid);

            $email = $firebaseUser->email ?? null;
            $name = $firebaseUser->displayName ?? 'Utilisateur';

            if (!$email) {
                return response()->json([
                    'error' => 'Email requis pour la connexion Google'
                ], 400);
            }

            // Découper le nom complet
            $nameParts = explode(' ', $name);
            $firstName = $nameParts[0] ?? 'Utilisateur';
            $lastName = $nameParts[1] ?? '';

            // Rechercher l'utilisateur par email
            $user = User::where('email', $email)->first();

            // Créer l'utilisateur s'il n'existe pas
            if (!$user) {
                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => null, // Pas de téléphone pour Google
                    'password' => bcrypt(uniqid()), // Mot de passe fictif
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
                // Mise à jour si déjà existant
                $user->is_online = true;
                $user->last_login = now();
                $user->save();
            }

            // Générer le token JWT
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
                'message' => 'Connexion Google réussie',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role_id' => $user->role_id,
                    'is_active' => $user->is_active,
                    'verified' => $user->verified,
                    'last_login' => $user->last_login,
                ],
                'token' => $token,
                'token_expiration' => $tokenExpiration
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Token Firebase invalide',
                'message' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if ($user) {
                // Mettre à jour le statut
                $user->is_online = false;
                $user->save();

                // Supprimer le token d'authentification
                Authentication::where('user_id', $user->id)->delete();
            }

            // Invalider le token JWT
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'message' => 'Déconnexion réussie'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Erreur lors de la déconnexion',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le statut de l'utilisateur connecté
     */
    public function me(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non trouvé'
                ], 404);
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role_id' => $user->role_id,
                    'is_active' => $user->is_active,
                    'verified' => $user->verified,
                    'is_online' => $user->is_online,
                    'last_login' => $user->last_login,
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Token invalide',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}