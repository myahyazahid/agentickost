<?php

namespace App\Modules\Tenancy\Database\Factories;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Kost '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'default_timezone' => 'Asia/Jakarta',
        ];
    }

    public function frozen(): static
    {
        return $this->state(fn (): array => ['frozen_at' => now()]);
    }
}
