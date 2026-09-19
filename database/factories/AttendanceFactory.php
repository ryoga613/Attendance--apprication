<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_date' => $this->faker->dateTimeBetween('-5 months', 'now')->format('Y-m-d'),
            'clock_in_at' => $this->faker->dateTime('09:00:00')->format('H:i:s'),
            'clock_out_at' => $this->faker->dateTime('18:00:00')->format('H:i:s'),
            'user_id' => User::where('email', 'user1@example.com')->first()->id,
        ];
    }
}
