<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Format: 2026 + 5 random digits, e.g. 202601234
            'student_number' => '2026'.$this->faker->unique()->numerify('#####'),

            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional(0.8)->lastName(), // 80% have a middle name
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            // Format: 09 + 9 random digits, e.g. 09171234567
            'contact_num' => '09'.$this->faker->numerify('#########'),
        ];
    }

    /**
     * Indicate that the student has no student number (walk-in / old ledger case).
     */
    public function withoutStudentNumber(): static
    {
        return $this->state(fn (array $attributes) => [
            'student_number' => null,
        ]);
    }
}