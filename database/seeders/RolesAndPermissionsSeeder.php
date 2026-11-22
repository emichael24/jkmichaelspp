<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------
        | Create permissions
        |--------------------------------------------------------------
        */
        $permissions = [
            'manage_users',
            'manage_courses',
            'manage_cohorts',
            'grade_assignments',
            'review_submissions',
            'view_org_reports',
            'manage_billing',
            'manage_content_workflow',
            'manage_question_bank',
            'view_learner_360',
            'manage_internships',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        /*
        |--------------------------------------------------------------
        | Create roles & assign permissions
        |--------------------------------------------------------------
        */

        // Platform Admin — full access
        Role::firstOrCreate(['name' => 'platform_admin'])
            ->givePermissionTo(Permission::all());

        // Organization Admin
        Role::firstOrCreate(['name' => 'org_admin'])
            ->givePermissionTo([
                'manage_users',
                'manage_cohorts',
                'view_org_reports',
                'manage_billing',
                'view_learner_360',
                'manage_internships',
            ]);

        // Facilitator (Trainer/Coach)
        Role::firstOrCreate(['name' => 'facilitator'])
            ->givePermissionTo([
                'manage_cohorts',
                'grade_assignments',
                'view_learner_360',
            ]);

        // SME Reviewer
        Role::firstOrCreate(['name' => 'sme_reviewer'])
            ->givePermissionTo([
                'manage_courses',
                'grade_assignments',
                'manage_content_workflow',
                'manage_question_bank',
            ]);

        // Sponsor (Internship reviewer)
        Role::firstOrCreate(['name' => 'sponsor'])
            ->givePermissionTo([
                'review_submissions',
            ]);

        // Line Manager
        Role::firstOrCreate(['name' => 'line_manager'])
            ->givePermissionTo([
                'view_org_reports',
            ]);

        // Learner — default role, no admin permissions
        Role::firstOrCreate(['name' => 'learner']);
    }
}
