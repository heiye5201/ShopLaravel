<?php

namespace Modules\Admin\Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Admin\Models\MarketingCampaign;

class MarketingCampaignFactory extends Factory
{
    protected $model = MarketingCampaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true), 'headline' => '发现日常好物',
            'body' => '看看为你精选的商品。', 'cta' => '查看商品', 'theme' => 'lavender',
            'position' => 'all', 'product_ids' => fn () => [Product::factory()->create()->id],
        ];
    }
}
