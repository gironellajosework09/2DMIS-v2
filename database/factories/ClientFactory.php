<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lastname' => $this->faker->lastName(),
            'firstname' => $this->faker->firstName(),
            'city_municipality' => 1,
            'barangay' => 1,
            'birthdate' => '2000-01-01',
            'age' => 26,
            'category' => 'YOUTH (18-29)',
            'sex' => 'MALE',
            'civil_status' => 'SINGLE',
            'pwd' => 'NO',
            'ip' => 'NO',
            'aff_org' => '',
        ];
    }
}
