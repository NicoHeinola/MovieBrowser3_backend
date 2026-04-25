<?php

namespace Database\Seeders;

use App\Models\Show\Show;
use Illuminate\Database\Seeder;

class ShowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Show::factory(30)->create();
    }
}
