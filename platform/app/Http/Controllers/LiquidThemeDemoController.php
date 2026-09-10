<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Liquid\Template;

final class LiquidThemeDemoController extends Controller
{
    public function home(Template $template): Response
    {
        return $this->render($template, 'home', [
            'page' => [
                'title' => '主题首页',
                'description' => '同一个 Layout 负责页面骨架，Partial 负责公共区域，Snippet 负责可重复的数据组件。',
            ],
            'products' => $this->products(),
        ]);
    }

    public function product(Template $template): Response
    {
        return $this->render($template, 'product', [
            'page' => [
                'title' => '商品详情',
                'description' => '这个详情页与主题首页继承同一个布局，并共享同一套页头和页尾。',
            ],
            'product' => $this->products()[0],
        ]);
    }

    private function render(Template $template, string $view, array $assigns): Response
    {
        $source = File::get(resource_path("liquid/theme/templates/{$view}.liquid"));

        $html = $template->parse($source)->render(array_merge([
            'shop' => [
                'name' => 'Aurora Objects',
                'tagline' => '让日常用品拥有一点未来感。',
            ],
            'navigation' => [
                ['title' => '首页', 'url' => '/liquid-theme-demo'],
                ['title' => '商品详情', 'url' => '/liquid-theme-demo/product'],
                ['title' => 'Filter', 'url' => '/liquid-filters-demo'],
                ['title' => 'Tags', 'url' => '/liquid-tags-demo'],
            ],
        ], $assigns));

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function products(): array
    {
        return [
            [
                'title' => 'Aurora 随行音箱',
                'description' => '轻巧、防水，适合带去任何地方。',
                'price' => 699.5,
                'image' => '/images/aurora-speaker.svg',
                'badge' => '主题推荐',
                'url' => '/liquid-theme-demo/product',
            ],
            [
                'title' => 'Aurora Studio 音箱',
                'description' => '为桌面空间准备的沉浸声场。',
                'price' => 1299,
                'image' => '/images/aurora-speaker.svg',
                'badge' => '新品',
                'url' => '/liquid-theme-demo/product',
            ],
            [
                'title' => 'Aurora Mini 音箱',
                'description' => '更紧凑的尺寸，同样清晰的声音。',
                'price' => 399,
                'image' => '/images/aurora-speaker.svg',
                'badge' => '轻量款',
                'url' => '/liquid-theme-demo/product',
            ],
        ];
    }
}
