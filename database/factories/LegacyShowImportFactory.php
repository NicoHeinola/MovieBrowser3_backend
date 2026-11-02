<?php

namespace Database\Factories;

use App\Models\LegacyShowImport\LegacyShowImport;
use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegacyShowImport>
 */
class LegacyShowImportFactory extends Factory
{
    protected $model = LegacyShowImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legacy_show_id' => fake()->unique()->numberBetween(1, 1000000),
            'show_id' => Show::factory(),
        ];
    }
}
