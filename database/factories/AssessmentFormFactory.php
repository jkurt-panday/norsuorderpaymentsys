<?php

namespace Database\Factories;

use App\Models\AssessmentForm;
use App\Models\Courses;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentForm>
 */
class AssessmentFormFactory extends Factory
{

    // at the top of the factory class
    protected static ?array $courseIds = null;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$courseIds ??= \App\Models\Courses::pluck('id')->all();

        $createdAt = $this->faker->dateTimeBetween(now()->startOfMonth(), now()->endOfMonth());
        
        return [
            'reference_number' => sprintf('%s-%s-AF-%s', now()->format('Y'), now()->format('m'), $this->faker->unique()->numerify('###')),
            'email' => $this->faker->unique()->safeEmail(),
            'contact_num' => $this->faker->numerify('09#########'),
            'first_name' => $this->faker->firstName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'last_name' => $this->faker->lastName(),
            'course_id' => $this->faker->randomElement(static::$courseIds),
            'address' => $this->faker->address(),
            'enrolled_under' => $this->faker->randomElement(['Graduate', 'School of Law', 'Undergraduate']),
            'sy_last_attended' => $this->faker->randomElement(['2021-2022', '2022-2023', '2023-2024', '2024-2025']),
            'semester' => $this->faker->randomElement(['First Semester', 'Second Semester', 'Summer']),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
