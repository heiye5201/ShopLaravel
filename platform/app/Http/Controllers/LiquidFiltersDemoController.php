<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Liquid\Template;

final class LiquidFiltersDemoController extends Controller
{
    public function __invoke(Request $request, Template $template): Response
    {
        $locale = in_array($request->query('locale'), ['zh_CN', 'en'], true)
            ? $request->query('locale')
            : 'zh_CN';

        app()->setLocale($locale);

        $source = File::get(resource_path('liquid/filters-demo.liquid'));

        $html = $template->parse($source)->render([
            'page' => [
                'locale' => $locale,
                'currency' => $locale === 'en' ? 'USD' : 'CNY',
                'timezone' => $locale === 'en' ? 'America/New_York' : 'Asia/Shanghai',
            ],
            'product' => [
                'title' => 'Aurora Speaker',
                'price' => 699.5,
                'published_at' => '2026-09-10T02:30:00+00:00',
                'image' => '/images/aurora-speaker.svg',
                'translation_key' => 'liquid_filters.stock.available',
            ],
        ]);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
