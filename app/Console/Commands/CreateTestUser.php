<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;

class CreateTestUser extends Command
{
    protected $signature = 'test:create-user';
    protected $description = 'Create a test user with API token';

    public function handle()
    {
        $existingUser = User::where('email', 'test@dabapp.com')->first();

        if ($existingUser) {
            $this->warn('⚠️  User already exists!');
            $this->info('Email: test@dabapp.com');
            $this->info('Password: password123');

            // Générer un nouveau token
            $token = $existingUser->createToken('test-token-' . time())->plainTextToken;
            $this->newLine();
            $this->warn('🔑 New Token:');
            $this->line($token);
            $this->newLine();

            return 0;
        }

        // Récupérer le role_id (user normal, pas admin)
        $role = Role::where('name', 'user')->first();

        if (!$role) {
            // Si pas de role 'user', prendre le premier role non-admin
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

        // Créer les préférences de notification par défaut
        \App\Models\NotificationPreference::create([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->info('✅ User created successfully!');
        $this->info('Email: test@dabapp.com');
        $this->info('Password: password123');
        $this->info('Role: ' . $role->name);
        $this->newLine();
        $this->warn('🔑 Token:');
        $this->line($token);
        $this->newLine();
        $this->info('💡 Copy this token for Postman Authorization header:');
        $this->line('Bearer ' . $token);

        return 0;
    }
}
