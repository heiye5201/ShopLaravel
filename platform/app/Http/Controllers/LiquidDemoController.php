<?php

namespace App\Http\Controllers;

use App\Liquid\Drops\ProductDrop;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Liquid\Template;

class LiquidDemoController extends Controller
{
    public function __invoke(Template $template): Response
    {
        $product = Product::query()
            ->with('variants')
            ->where('slug', 'aurora-speaker')
            ->firstOrFail();

        $source = File::get(resource_path('liquid/product.liquid'));

        $html = $template->parse($source)->render([
            'page' => [
                'eyebrow' => 'liquid lab',
                'title' => 'Liquid 商品展示页',
                'description' => '这个页面由 Laravel 提供数据，并由 Liquid 模板完成渲染。',
            ],
            'product' => new ProductDrop($product),
        ]);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
