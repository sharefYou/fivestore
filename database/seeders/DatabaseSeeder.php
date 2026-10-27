<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * No administrator is created here on purpose: a seeded account would mean
     * a password living in the repository. Run `php artisan app:create-admin`
     * instead, which asks for one that is never written down.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        $this->command?->newLine();
        $this->command?->info('Catalog seeded. Create an administrator with: php artisan app:create-admin');
    }
}
