<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ContentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            [
                'email' => 'eljuegoperfectomx13@gmail.com',
            ],
            [
                'name' => 'El Juego Perfecto MX',
                'email' => 'eljuegoperfectomx13@gmail.com',
                'password' => bcrypt('S3v0y4$123'),
            ]
        );

        ContentType::firstOrCreate(['name' => 'Artículo', 'slug' => 'article']);
        ContentType::firstOrCreate(['name' => 'Video', 'slug' => 'video']);
    }
}
