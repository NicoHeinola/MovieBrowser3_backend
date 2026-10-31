<?php

namespace Database\Factories;

use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteTag>
 */
class WebsiteTagFactory extends Factory
{
    protected $model = WebsiteTag::class;

    /**
     * @return array<string, string>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
