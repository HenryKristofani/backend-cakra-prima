<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$u = User::firstOrCreate(
    ['email' => 'testuser@example.com'], 
    ['name' => 'Test User', 'password' => Hash::make('password')]
);
$u->role = 'user';
$u->save();

echo "User created: ID {$u->id}, Role: {$u->role}\n";
