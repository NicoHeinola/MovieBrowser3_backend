<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $email = env('ADMIN_USER_EMAIL', 'admin@example.com');
        $name = env('ADMIN_USER_NAME', 'Admin User');
        $password = env('ADMIN_USER_PASSWORD', 'password');
        $now = now();

        $existingAdmin = DB::table('users')
            ->where('email', $email)
            ->first();

        if ($existingAdmin !== null) {
            DB::table('users')
                ->where('id', $existingAdmin->id)
                ->update([
                    'is_admin' => true,
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('users')->insert([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $now,
            'password' => Hash::make($password),
            'is_admin' => true,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Intentionally non-destructive: rolling this back should not delete or demote real user data.
    }
};
