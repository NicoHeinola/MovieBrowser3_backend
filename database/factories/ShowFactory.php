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
        $youtubeIds = [
            '0HjdiohVOik',
            'V-_O7nl0Ii0',
            'y6120QOlsfU',
            'fJ9rUzIMcZQ',
            'OPf0YbXqDm0',
        ];

        return [
            'banner_url' => 'https://picsum.photos/'.fake()->numberBetween(1000, 1400).'/'.fake()->numberBetween(1000, 1400),
            'card_image_url' => 'https://picsum.photos/'.fake()->numberBetween(400, 600).'/'.fake()->numberBetween(400, 600),
            'preview_url' => fake()->boolean() ? 'https://www.youtube.com/watch?v='.fake()->randomElement($youtubeIds) : null,
            'description' => fake()->paragraph(fake()->numberBetween(3, 15)),
        ];
    }
}
