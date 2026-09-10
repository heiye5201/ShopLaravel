<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class LiquidDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $product = Product::query()->updateOrCreate(
            ['slug' => 'aurora-speaker'],
            [
                'title' => 'Aurora 随行音箱',
                'price' => 699,
                'description' => '轻巧机身，支持 <script>alert("demo")</script> 全天候音乐播放。',
                'is_available' => true,
                'tags' => ['蓝牙 5.4', 'IP67 防水', '24 小时续航'],
            ],
        );

        foreach ([
            ['title' => '午夜蓝', 'price' => 699, 'is_available' => true, 'position' => 1],
            ['title' => '晨雾白', 'price' => 699, 'is_available' => false, 'position' => 2],
            ['title' => '落日橙', 'price' => 729, 'is_available' => true, 'position' => 3],
        ] as $variant) {
            $product->variants()->updateOrCreate(
                ['title' => $variant['title']],
                $variant,
            );
        }
    }
}
