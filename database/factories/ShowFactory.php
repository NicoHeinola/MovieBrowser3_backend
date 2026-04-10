<?php

namespace Database\Factories;

use App\Models\Show\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Show>
 */
class ShowFactory extends Factory
{
    protected $model = Show::class;

    /**
     * @return array<string, string|null>
     */
    public function definition(): array
    {
        return [
            'banner_url' => fake()->url().'/banner-'.fake()->unique()->slug().'.jpg',
            'card_image_url' => fake()->url().'/card-'.fake()->unique()->slug().'.jpg',
            'preview_url' => fake()->boolean() ? fake()->url().'/preview-'.fake()->unique()->slug().'.mp4' : null,
            'description' => fake()->sentence(),
        ];
    }
}
