<?php

namespace Database\Factories;

use App\Enums\TeacherType;
use App\Enums\UserRole;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state (a student with no section).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Student,
            'teacher_type' => null,
            'section_id' => null,
        ];
    }

    public function developer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Developer, 'teacher_type' => null, 'section_id' => null]);
    }

    public function teacher(TeacherType $type = TeacherType::FullTime): static
    {
        return $this->state(fn () => ['role' => UserRole::Teacher, 'teacher_type' => $type, 'section_id' => null]);
    }

    public function student(?Section $section = null): static
    {
        return $this->state(fn () => ['role' => UserRole::Student, 'teacher_type' => null, 'section_id' => $section?->id]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
