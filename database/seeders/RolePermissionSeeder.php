<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Patient management
            'view-patients', 'create-patients', 'edit-patients', 'delete-patients',

            // Visit management
            'view-visits', 'create-visits', 'edit-visits', 'delete-visits',

            // Encounter management
            'view-encounters', 'create-encounters', 'edit-encounters', 'delete-encounters',

            // Observation management
            'view-observations', 'create-observations', 'edit-observations', 'delete-observations',

            // Clinical activities
            'record-vitals', 'prescribe-medications', 'administer-medications',
            'view-lab-results', 'create-lab-orders', 'view-imaging', 'create-imaging-orders',

            // Administrative
            'view-dashboard', 'manage-users', 'manage-system', 'view-reports',
            'export-data', 'import-data', 'manage-taxonomy', 'view-system-logs',

            // Billing and finance
            'view-invoices', 'create-invoices', 'edit-invoices', 'process-payments',

            // Scheduling
            'view-schedules', 'create-appointments', 'edit-appointments', 'cancel-appointments'
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Create roles and assign permissions

        // Super Admin Role
        $superAdmin = Role::create([
            'name' => 'super-admin',
            'guard_name' => 'api'
        ]);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin Role
        $admin = Role::create([
            'name' => 'admin',
            'guard_name' => 'api'
        ]);
        $admin->givePermissionTo([
            'view-patients', 'create-patients', 'edit-patients',
            'view-visits', 'create-visits', 'edit-visits',
            'view-encounters', 'create-encounters', 'edit-encounters',
            'view-observations', 'create-observations', 'edit-observations',
            'view-dashboard', 'manage-users', 'view-reports',
            'export-data', 'import-data', 'manage-taxonomy',
            'view-invoices', 'create-invoices', 'edit-invoices',
            'view-schedules', 'create-appointments', 'edit-appointments'
        ]);

        // Doctor Role
        $doctor = Role::create([
            'name' => 'doctor',
            'guard_name' => 'api'
        ]);
        $doctor->givePermissionTo([
            'view-patients', 'create-patients', 'edit-patients',
            'view-visits', 'create-visits', 'edit-visits',
            'view-encounters', 'create-encounters', 'edit-encounters',
            'view-observations', 'create-observations', 'edit-observations',
            'record-vitals', 'prescribe-medications',
            'view-lab-results', 'create-lab-orders',
            'view-imaging', 'create-imaging-orders',
            'view-dashboard', 'view-reports',
            'view-invoices', 'create-invoices',
            'view-schedules', 'create-appointments', 'edit-appointments'
        ]);

        // Nurse Role
        $nurse = Role::create([
            'name' => 'nurse',
            'guard_name' => 'api'
        ]);
        $nurse->givePermissionTo([
            'view-patients', 'edit-patients',
            'view-visits', 'edit-visits',
            'view-encounters', 'create-encounters', 'edit-encounters',
            'view-observations', 'create-observations', 'edit-observations',
            'record-vitals', 'administer-medications',
            'view-lab-results', 'view-imaging',
            'view-dashboard',
            'view-schedules', 'create-appointments', 'edit-appointments'
        ]);

        // Technician Role
        $technician = Role::create([
            'name' => 'technician',
            'guard_name' => 'api'
        ]);
        $technician->givePermissionTo([
            'view-patients',
            'view-visits',
            'view-encounters',
            'view-observations', 'create-observations', 'edit-observations',
            'view-lab-results', 'view-imaging',
            'view-dashboard'
        ]);

        // Pharmacist Role
        $pharmacist = Role::create([
            'name' => 'pharmacist',
            'guard_name' => 'api'
        ]);
        $pharmacist->givePermissionTo([
            'view-patients',
            'view-visits',
            'view-encounters',
            'view-observations',
            'administer-medications',
            'view-dashboard'
        ]);

        // Receptionist Role
        $receptionist = Role::create([
            'name' => 'receptionist',
            'guard_name' => 'api'
        ]);
        $receptionist->givePermissionTo([
            'view-patients', 'create-patients', 'edit-patients',
            'view-visits', 'create-visits',
            'view-dashboard',
            'view-schedules', 'create-appointments', 'edit-appointments', 'cancel-appointments',
            'view-invoices', 'create-invoices', 'process-payments'
        ]);

        $user = User::create([
            'name' => 'Vengence',
            'email' => 'vengence@openpulse.org',
            'username' => 'vengence',
            'password' => bcrypt('password'),
            'email_verified_at' => '2025-07-07 16:24:52'
        ]);

//        $user->assignRole('doctor');

        $user->givePermissionTo([
            'manage-system', 'view-system-logs', 'manage-taxonomy'
        ]);

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Default user "Vengence" created with doctor role and additional admin permissions.');
    }
}
