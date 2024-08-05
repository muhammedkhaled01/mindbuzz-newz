<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    private $permissions = [
        // 'role-list',
        'role-create',
        'role-edit',
        'role-delete',
        'class-list',
        'class-create',
        'class-edit',
        'class-delete'
    ];
    public function run(): void
    {
        // $this->call([
        //     SchoolSeeder::class,
        //     ClassSeeder::class,
        //     CourseSeeder::class,
        //     StageSeeder::class,
        //     ProgramSeeder::class,
        // ]);
        foreach ($this->permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create admin User and assign the role to him.
        $user = User::create([
            'name' => 'Prevail Ejimadu',
            'email' => 'admin@admin.com',
            'password' => Hash::make('123456'),
            'phone' => "123",
            'role' => 3,
        ]);

        $role = Role::create(['name' => 'Admin']);

        $permissions = Permission::pluck('id', 'id')->all();

        $role->syncPermissions($permissions);

        $user->assignRole([$role->id]);
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}