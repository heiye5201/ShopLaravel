<?php

namespace Modules\Admin\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admin\Models\AdvancedOfferStyle;

class AdvancedOfferStyleFactory extends Factory
{
    protected $model = AdvancedOfferStyle::class;

    public function definition(): array
    {
        return [...AdvancedOfferStyle::defaults(), 'name' => fake()->words(3, true)];
    }
}
