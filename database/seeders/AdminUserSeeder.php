<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create a default organization
        $org = Organization::firstOrCreate(
            ['slug' => 'jk-michaels'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'JK Michaels Consulting',
                'contact_email' => 'admin@jkmichaels.com',
                'industry' => 'Professional Training',
                'size' => '51-200',
                'country' => 'NGA',
                'subscription_plan' => 'enterprise',
                'max_seats' => 500,
                'is_active' => true,
            ]
        );

        // Create platform admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@jkmichaels.com'],
            [
                'first_name' => 'Platform',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'organization_id' => $org->id,
                'is_active' => true,
                'onboarding_completed' => true,
            ]
        );

        $admin->assignRole('platform_admin');

        // Create a demo learner
        $learner = User::firstOrCreate(
            ['email' => 'learner@jkmichaels.com'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Learner',
                'password' => Hash::make('password'),
                'organization_id' => $org->id,
                'is_active' => true,
                'goal' => 'certification',
                'daily_minutes' => 30,
                'learning_preference' => 'read',
                'experience_level' => 'intermediate',
            ]
        );

        $learner->assignRole('learner');

        $this->command->info('Admin user created: admin@jkmichaels.com / password');
        $this->command->info('Learner user created: learner@jkmichaels.com / password');
    }
}
