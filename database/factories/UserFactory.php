<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'google_id' => (string) $this->faker->unique()->numerify('####################'),
            'avatar' => null,
            'role' => Role::Participante(),
            'email_verified_at' => now(),
        ];
    }

    public function admin(): self
    {
        return $this->state(['role' => Role::Admin()]);
    }

    public function operador(): self
    {
        return $this->state(['role' => Role::Operador()]);
    }
}
