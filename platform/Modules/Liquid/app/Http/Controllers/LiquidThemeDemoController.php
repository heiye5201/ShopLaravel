<?php

namespace Modules\Liquid\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Liquid\Template;
use Modules\Liquid\Services\LiquidDemoPageService;

class LiquidThemeDemoController extends Controller
{
    public function __construct(private readonly LiquidDemoPageService $pages) {}

    public function home(Template $template): Response
    {
        $html = $this->pages->renderThemeHome($template);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function category(Template $template, Category $category): Response
    {
        return response($this->pages->renderThemeCategory($template, $category))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function product(Request $request, Template $template): Response
    {
        $data = $request->validate(['slug' => ['nullable', 'string', 'max:255']]);
        $html = $this->pages->renderThemeProduct($template, $data['slug'] ?? null);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
