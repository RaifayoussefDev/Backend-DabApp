<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;

class CreateTestUser extends Command
{
    protected $signature = 'test:create-user';
    protected $description = 'Create a test user with JWT token';

    public function handle()
    {
        $existingUser = User::where('email', 'test@dabapp.com')->first();

        if ($existingUser) {
            $this->warn('⚠️  User already exists!');
            $this->info('Email: test@dabapp.com');
            $this->info('Password: password123');

            // Générer un token JWT
            $token = JWTAuth::fromUser($existingUser);
            $this->newLine();
            $this->warn('🔑 JWT Token:');
            $this->line($token);
            $this->newLine();
            $this->info('💡 Copy for Postman Authorization:');
            $this->line('Bearer ' . $token);

            return 0;
        }

        // Récupérer le role_id
        $role = Role::where('name', 'user')->first();

        if (!$role) {
            $role = Role::where('id', '!=', 1)->first();
        }

        if (!$role) {
            $this->error('❌ No role found! Please seed roles first.');
            return 1;
        }

        // Créer le nouvel utilisateur
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@dabapp.com',
            'password' => bcrypt('password123'),
            'phone' => '0612345678',
            'role_id' => $role->id,
            'verified' => true,
            'is_active' => true,
            'language' => 'en',
        ]);

        // Créer les préférences de notification
        \App\Models\NotificationPreference::create([
            'user_id' => $user->id,
        ]);

        // Générer un token JWT
        $token = JWTAuth::fromUser($user);

        $this->info('✅ User created successfully!');
        $this->info('Email: test@dabapp.com');
        $this->info('Password: password123');
        $this->info('Role: ' . $role->name);
        $this->newLine();
        $this->warn('🔑 JWT Token:');
        $this->line($token);
        $this->newLine();
        $this->info('💡 Copy for Postman Authorization:');
        $this->line('Bearer ' . $token);

        return 0;
    }
}
