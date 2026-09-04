<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear los roles
        $master_role = Role::firstOrCreate(['name' => 'Master']);
        $admin_role = Role::firstOrCreate(['name' => 'Administrador']);
        $blogger_role = Role::firstOrCreate(['name' => 'Editor']);
        $guest_role = Role::firstOrCreate(['name' => 'Invitado']);

        $master_role->syncPermissions([]);
        $admin_role->syncPermissions([]);
        $blogger_role->syncPermissions([]);
        $guest_role->syncPermissions([]);

        // 2. Crear tu usuario administrador
        $masterUser = User::firstOrCreate(
            [
                'email' => 'eljuegoperfectomx13@gmail.com',
            ],
            [
                'name' => 'El Juego Perfecto MX',
                'email' => 'eljuegoperfectomx13@gmail.com',
                'password' => bcrypt('S3v0y4$123'),
            ]
        );

        // 3. Asignarle el rol Master
        $masterUser->assignRole('Master');


        // MASTER
        $master_permissions = [
            ['name' => 'manage users'],
        ];

        // ADMINISTRATOR
        $admin_permissions = [
            ['name' => 'create teams'],
            ['name' => 'read teams'],
            ['name' => 'update teams'],
            ['name' => 'delete teams'],

            ['name' => 'create players'],
            ['name' => 'read players'],
            ['name' => 'update players'],
            ['name' => 'delete players'],

            ['name' => 'create tournaments'],
            ['name' => 'read tournaments'],
            ['name' => 'update tournaments'],
            ['name' => 'delete tournaments'],

            ['name' => 'create calendar'],
            ['name' => 'read calendar'],
            ['name' => 'update calendar'],
            ['name' => 'delete calendar'],

            ['name' => 'create teams'],
            ['name' => 'read teams'],
            ['name' => 'update teams'],
            ['name' => 'delete teams'],

            ['name' => 'create games'],
            ['name' => 'read games'],
            ['name' => 'update games'],
            ['name' => 'delete games'],
            
            ];
            
        // BLOGGER
        $blogger_permissions = [
            ['name' => 'create posts'],
            ['name' => 'edit posts'],
            ['name' => 'update posts'],
            ['name' => 'delete posts'],
        ];

        // MASTER
        foreach($master_permissions as $row) {
            Permission::firstOrCreate(['name' => $row['name']]);
            $master_role->givePermissionTo($row['name']);
        }

        // ADMINISTRADOR
        foreach($admin_permissions as $row) {
            Permission::firstOrCreate(['name' => $row['name']]);
            $master_role->givePermissionTo($row['name']);
            $admin_role->givePermissionTo($row['name']);
        }

        // BLOGGER
        foreach($blogger_permissions as $row) {
            Permission::firstOrCreate(['name' => $row['name']]);
            $master_role->givePermissionTo($row['name']);
            $admin_role->givePermissionTo($row['name']);
            $blogger_role->givePermissionTo($row['name']);
        }
    }
}
