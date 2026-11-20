<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Мероприятие '.fake()->unique()->numberBetween(1, 99999),
            'category' => fake()->randomElement(array_keys(Event::CATEGORIES)),
            'starts_at' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'city' => fake()->randomElement(['Костанай', 'Астана', 'Алматы']),
            'venue' => 'Дворец культуры',
            'price' => fake()->numberBetween(20, 200) * 100,
            'tickets_total' => 100,
            'available_tickets' => 100,
            'description' => null,
        ];
    }
}
