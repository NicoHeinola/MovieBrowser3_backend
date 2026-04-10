<?php

namespace Database\Factories;

use App\Models\Setting\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['string', 'number', 'json']);
        $value = match ($type) {
            'number' => $this->faker->randomNumber(),
            'json' => ['foo' => 'bar'],
            default => $this->faker->word(),
        };

        return [
            'key' => $this->faker->unique()->word(),
            'type' => $type,
            'value' => $value,
        ];
    }
}
