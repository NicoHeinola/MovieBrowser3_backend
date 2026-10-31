<?php

namespace Database\Factories;

use App\Models\Website\Website;
use App\Models\WebsiteTag\WebsiteTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteTag>
 */
class WebsiteTagFactory extends Factory
{
    protected $model = WebsiteTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
