<?php

namespace Modules\Liquid\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\File;
use Liquid\Template;
use Modules\Liquid\Drops\ProductDrop;

final class LiquidDemoPageService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function renderProduct(Template $template): string
    {
        $product = $this->products->findBySlugOrFail('aurora-speaker', ['variants']);

        return $this->render($template, 'product.liquid', [
            'page' => [
                'eyebrow' => 'liquid lab',
                'title' => 'Liquid 商品展示页',
                'description' => '这个页面由 Laravel 提供数据，并由 Liquid 模板完成渲染。',
            ],
            'product' => new ProductDrop($product),
        ]);
    }

    public function renderFilters(Template $template, mixed $requestedLocale): string
    {
        $locale = in_array($requestedLocale, ['zh_CN', 'en'], true)
            ? $requestedLocale
            : 'zh_CN';

        app()->setLocale($locale);

        $product = $this->products->findBySlugOrFail('aurora-speaker');

        return $this->render($template, 'filters-demo.liquid', [
            'page' => [
                'locale' => $locale,
                'currency' => $locale === 'en' ? 'USD' : 'CNY',
                'timezone' => $locale === 'en' ? 'America/New_York' : 'Asia/Shanghai',
            ],
            'product' => [
                'title' => $product->title,
                'price' => (float) $product->price,
                'published_at' => $product->created_at?->toIso8601String(),
                'image' => $product->image_path,
                'translation_key' => $product->is_available
                    ? 'liquid_filters.stock.available'
                    : 'liquid_filters.stock.sold_out',
            ],
        ]);
    }

    public function renderTags(Template $template): string
    {
        $products = $this->products
            ->getProducts()
            ->map(fn (Product $product): array => [
                'title' => $product->title,
                'price' => (float) $product->price,
            ])
            ->all();

        return $this->render($template, 'tags-demo.liquid', [
            'shop' => ['name' => '北辰商店'],
            'campaign' => [
                'status' => 'active',
                'discount' => 20,
            ],
            'customer' => ['vip' => false],
            'products' => $products,
        ]);
    }

    public function renderThemeHome(Template $template): string
    {
        $products = $this->products
            ->getProducts()
            ->map(fn (Product $product): array => $this->toLiquidProduct($product))
            ->all();

        return $this->renderTheme($template, 'home', [
            'page' => [
                'title' => '主题首页',
                'description' => '同一个 Layout 负责页面骨架，Partial 负责公共区域，Snippet 负责可重复的数据组件。',
            ],
            'products' => $products,
        ]);
    }

    public function renderThemeProduct(Template $template): string
    {
        $product = $this->products->findBySlugOrFail('aurora-speaker');

        return $this->renderTheme($template, 'product', [
            'page' => [
                'title' => '商品详情',
                'description' => '这个详情页与主题首页继承同一个布局，并共享同一套页头和页尾。',
            ],
            'product' => $this->toLiquidProduct($product),
        ]);
    }

    private function renderTheme(Template $template, string $view, array $assigns): string
    {
        return $this->render($template, "theme/templates/{$view}.liquid", array_merge([
            'shop' => [
                'name' => 'Aurora Objects',
                'tagline' => '让日常用品拥有一点未来感。',
            ],
            'navigation' => [
                ['title' => 'Liquid 首页', 'url' => route('liquid.home')],
                ['title' => '首页', 'url' => route('liquid.theme-demo')],
                ['title' => '商品详情', 'url' => route('liquid.theme-demo.product')],
                ['title' => 'Filter', 'url' => route('liquid.filters-demo')],
                ['title' => 'Tags', 'url' => route('liquid.tags-demo')],
            ],
        ], $assigns));
    }

    private function toLiquidProduct(Product $product): array
    {
        return [
            'title' => $product->title,
            'description' => $product->description,
            'price' => (float) $product->price,
            'image' => $product->image_path,
            'badge' => $product->badge,
            'url' => route('liquid.theme-demo.product'),
        ];
    }

    private function render(Template $template, string $view, array $assigns): string
    {
        $source = File::get(module_path('Liquid', "resources/liquid/{$view}"));

        return $template->parse($source)->render($assigns);
    }
}
