<?php

namespace Database\Factories;

use App\Enums\ShowLinkType;
use App\Models\Show\Show;
use App\Models\ShowLink\ShowLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowLink>
 */
class ShowLinkFactory extends Factory
{
    protected $model = ShowLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_show_id' => Show::factory(),
            'target_show_id' => Show::factory(),
            'type' => ShowLinkType::Sequel,
        ];
    }

    public function prequel(): static
    {
        return $this->state(fn (): array => ['type' => ShowLinkType::Prequel]);
    }

    public function tvSpecial(): static
    {
        return $this->state(fn (): array => ['type' => ShowLinkType::TvSpecial]);
    }

    public function suggestedNext(): static
    {
        return $this->state(fn (): array => ['type' => ShowLinkType::SuggestedNext]);
    }

    public function spinOff(): static
    {
        return $this->state(fn (): array => ['type' => ShowLinkType::SpinOff]);
    }
}
