<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Organisation;
use App\Models\Department;
use App\Models\WorkType;
use App\Models\ProjectClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Roles and Permissions
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(LetterTemplateSeeder::class);

        // 2. Default Organisation with branding
        $org = Organisation::create([
            'name' => 'WorkTrack Demo Org',
            'slug' => 'worktrack-demo',
            'is_active' => true,
            'primary_color' => '#0d9488',
            'secondary_color' => '#6366f1',
        ]);

        // 3. Seed Default Work Types for this org
        $workTypes = [
            ['name' => 'Direct', 'color' => 'success'],
            ['name' => 'Indirect', 'color' => 'warning'],
            ['name' => 'Growth', 'color' => 'info'],
        ];
        foreach ($workTypes as $wt) {
            WorkType::create([
                'organisation_id' => $org->id,
                'name' => $wt['name'],
                'color' => $wt['color'],
                'is_active' => true,
            ]);
        }

        // 4. Seed Default Project/Clients for this org
        $clients = [
            ['name' => 'Internal', 'description' => 'Internal company tasks'],
            ['name' => 'Acme Corp', 'description' => 'Acme Corporation projects'],
            ['name' => 'TechStart Inc', 'description' => 'TechStart startup project'],
        ];
        foreach ($clients as $client) {
            ProjectClient::create([
                'organisation_id' => $org->id,
                'name' => $client['name'],
                'description' => $client['description'],
                'is_active' => true,
            ]);
        }

        // 5. Departments
        $engineering = Department::create([
            'organisation_id' => $org->id,
            'name' => 'Engineering',
            'description' => 'Software development team',
        ]);

        $design = Department::create([
            'organisation_id' => $org->id,
            'name' => 'Design',
            'description' => 'Product design team',
        ]);

        // 6. Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'tienojunior84@gmail.com',
            'password' => Hash::make('password'),
            'organisation_id' => $org->id,
            'department_id' => null, // Cross-department
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ]);
        $superAdmin->assignRole('super_admin');

        // 7. Admins
        $engAdmin = User::create([
            'name' => 'Eng Manager',
            'email' => 'admin.eng@worktrackpro.test',
            'password' => Hash::make('password'),
            'organisation_id' => $org->id,
            'department_id' => $engineering->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $engAdmin->assignRole('admin');
        
        $engineering->update(['created_by' => $superAdmin->id]);
        $design->update(['created_by' => $superAdmin->id]);

        $designAdmin = User::create([
            'name' => 'Design Manager',
            'email' => 'admin.design@worktrackpro.test',
            'password' => Hash::make('password'),
            'organisation_id' => $org->id,
            'department_id' => $design->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $designAdmin->assignRole('admin');

        // 8. Workers
        $workers = [
            ['name' => 'Alice Dev', 'email' => 'worker1@worktrackpro.test', 'department_id' => $engineering->id],
            ['name' => 'Bob Dev', 'email' => 'worker2@worktrackpro.test', 'department_id' => $engineering->id],
            ['name' => 'Charlie Dev', 'email' => 'worker3@worktrackpro.test', 'department_id' => $engineering->id],
            ['name' => 'Diana UI', 'email' => 'worker4@worktrackpro.test', 'department_id' => $design->id],
            ['name' => 'Eve UX', 'email' => 'worker5@worktrackpro.test', 'department_id' => $design->id],
        ];

        foreach ($workers as $workerData) {
            $user = User::create([
                'name' => $workerData['name'],
                'email' => $workerData['email'],
                'password' => Hash::make('password'),
                'organisation_id' => $org->id,
                'department_id' => $workerData['department_id'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->assignRole('worker');
        }
    }
}
