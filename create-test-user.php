<?php

$user = \App\Models\User::create([
    'name' => 'Test User',
    'email' => 'test@dabapp.com',
    'password' => bcrypt('password123'),
    'phone' => '0612345678',
]);

$token = $user->createToken('test-token')->plainTextToken;

echo "\n✅ User created successfully!\n";
echo "Email: test@dabapp.com\n";
echo "Password: password123\n";
echo "Token: " . $token . "\n\n";
echo "Copy this token for Postman!\n";
