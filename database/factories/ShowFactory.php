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
            'banner_url' => 'https://picsum.photos/800/450',
            'card_image_url' => 'https://picsum.photos/200/300',
            'preview_url' => fake()->boolean() ? fake()->url().'/preview-'.fake()->unique()->slug().'.mp4' : null,
            'description' => fake()->paragraph(fake()->numberBetween(3, 15)),
        ];
    }
}
