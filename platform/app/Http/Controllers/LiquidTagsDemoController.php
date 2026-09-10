<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Liquid\Template;

final class LiquidTagsDemoController extends Controller
{
    public function __invoke(Template $template): Response
    {
        $source = File::get(resource_path('liquid/tags-demo.liquid'));

        $html = $template->parse($source)->render([
            'shop' => [
                'name' => '北辰商店',
            ],
            'campaign' => [
                'status' => 'active',
                'discount' => 20,
            ],
            'customer' => [
                'vip' => false,
            ],
            'products' => [
                ['title' => 'Aurora 随行音箱', 'price' => '¥699.00'],
                ['title' => 'Nova 桌面灯', 'price' => '¥299.00'],
                ['title' => 'Orbit 无线充', 'price' => '¥199.00'],
            ],
        ]);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
