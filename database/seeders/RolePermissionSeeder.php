<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api'
            ]);
        }

        // Create roles and assign permissions

        // Super Admin Role
        $superAdmin = Role::query()->firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'api'
        ]);
        $superAdmin->syncPermissions(Permission::all());

        // Admin Role
        $admin = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api'
        ]);
        $admin->syncPermissions([
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
        $doctor = Role::firstOrCreate([
            'name' => 'doctor',
            'guard_name' => 'api'
        ]);
        $doctor->syncPermissions([
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
        $nurse = Role::firstOrCreate([
            'name' => 'nurse',
            'guard_name' => 'api'
        ]);
        $nurse->syncPermissions([
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
        $technician = Role::firstOrCreate([
            'name' => 'technician',
            'guard_name' => 'api'
        ]);
        $technician->syncPermissions([
            'view-patients',
            'view-visits',
            'view-encounters',
            'view-observations', 'create-observations', 'edit-observations',
            'view-lab-results', 'view-imaging',
            'view-dashboard'
        ]);

        // Pharmacist Role
        $pharmacist = Role::firstOrCreate([
            'name' => 'pharmacist',
            'guard_name' => 'api'
        ]);
        $pharmacist->syncPermissions([
            'view-patients',
            'view-visits',
            'view-encounters',
            'view-observations',
            'administer-medications',
            'view-dashboard'
        ]);

        // Receptionist Role
        $receptionist = Role::firstOrCreate([
            'name' => 'receptionist',
            'guard_name' => 'api'
        ]);
        $receptionist->syncPermissions([
            'view-patients', 'create-patients', 'edit-patients',
            'view-visits', 'create-visits',
            'view-dashboard',
            'view-schedules', 'create-appointments', 'edit-appointments', 'cancel-appointments',
            'view-invoices', 'create-invoices', 'process-payments'
        ]);

        $user = User::firstOrCreate(
            ['email' => 'vengence@pulse.test'],
            [
                'name' => 'Vengence',
                'username' => 'vengence',
                'password' => Hash::make('password'),
                'email_verified_at' => '2025-07-07 16:24:52'
            ]
        );
        $user->ulid = $user->ulid ?: Str::ulid()->toBase32();
        $user->email_verified_at = now();
        $user->timestamps = false;
        $user->save();

        // Super admin role already has all permissions, no need for direct assignment
        $user->assignRole($superAdmin);

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Default user "Vengence" created with super-admin role.');
    }
}
