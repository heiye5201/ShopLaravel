<?php

namespace Modules\Liquid\Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class LiquidDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            'aurora-speaker' => [
                'title' => 'Aurora 随行音箱',
                'price' => 699.5,
                'description' => '轻巧机身，支持 <script>alert("demo")</script> 全天候音乐播放。',
                'image_path' => '/images/aurora-speaker.svg',
                'badge' => '主题推荐',
                'is_available' => true,
                'tags' => ['蓝牙 5.4', 'IP67 防水', '24 小时续航'],
            ],
            'aurora-studio-speaker' => [
                'title' => 'Aurora Studio 音箱',
                'price' => 1299,
                'description' => '为桌面空间准备的沉浸声场。',
                'image_path' => '/images/aurora-speaker.svg',
                'badge' => '新品',
                'is_available' => true,
                'tags' => ['空间音频', '桌面音箱'],
            ],
            'aurora-mini-speaker' => [
                'title' => 'Aurora Mini 音箱',
                'price' => 399,
                'description' => '更紧凑的尺寸，同样清晰的声音。',
                'image_path' => '/images/aurora-speaker.svg',
                'badge' => '轻量款',
                'is_available' => true,
                'tags' => ['轻巧', '便携'],
            ],
        ];

        foreach ($products as $slug => $attributes) {
            Product::query()->updateOrCreate(['slug' => $slug], $attributes);
        }

        $product = Product::query()->where('slug', 'aurora-speaker')->firstOrFail();

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
